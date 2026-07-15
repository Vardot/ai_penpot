<?php

declare(strict_types=1);

namespace Drupal\ai_penpot;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Fetches and summarizes Penpot design context for AI Agent tools.
 *
 * Talks to the Penpot RPC API (<base>/api/rpc/command/<command>) on a
 * self-hosted or Penpot Cloud instance. This is the server-side counterpart of
 * the Penpot MCP plugin's design reads, reachable from PHP without a browser.
 * The access token is resolved from the Key module (kept encrypted at rest via
 * easy_encryption) and sent in the Authorization: Token header.
 */
class PenpotContextClient {

  /**
   * A UUID (Penpot file/page ids), as used in Penpot URLs.
   */
  private const UUID = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

  /**
   * Constructs the client.
   */
  public function __construct(
    protected ClientInterface $httpClient,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected $keyRepository = NULL,
  ) {}

  /**
   * Resolves the Penpot access token from the configured Key.
   *
   * The token is stored only in the Key module - the same way Drupal AI keeps
   * its provider keys - so it benefits from encryption at rest (the
   * easy_encryption provider). There is no environment-variable path.
   *
   * @return string
   *   The token, or empty string if none configured.
   */
  public function getToken(): string {
    if (!$this->keyRepository) {
      return '';
    }
    $key_id = (string) $this->configFactory->get('ai_penpot.settings')->get('penpot_token_key');
    if ($key_id === '') {
      return '';
    }
    $key = $this->keyRepository->getKey($key_id);
    return $key ? (string) $key->getKeyValue() : '';
  }

  /**
   * Parses a Penpot workspace/view URL into a file id and page id.
   *
   * Accepts forms like:
   *   https://penpot.app/#/workspace/<teamId>/<fileId>?page-id=<pageId>
   *   https://penpot.app/#/workspace?team-id=<t>&file-id=<f>&page-id=<p>
   *   https://penpot.app/#/view/<fileId>?page-id=<p>&index=0
   *   a bare file id (UUID).
   *
   * @param string $url
   *   A Penpot link (or a bare file id).
   *
   * @return array
   *   ['file_id' => string, 'page_id' => string].
   */
  public static function parsePenpotUrl(string $url): array {
    $out = ['file_id' => '', 'page_id' => ''];
    $url = trim($url);
    if ($url === '') {
      return $out;
    }
    // AI prompts commonly "@"-prefix the link.
    $url = str_replace('@http', 'http', $url);
    $url = ltrim($url, '@');

    $uuid = self::UUID;

    // Explicit query params win: file-id / page-id (with dash or underscore).
    if (preg_match('#[?&]file[-_]id=(' . $uuid . ')#', $url, $m)) {
      $out['file_id'] = strtolower($m[1]);
    }
    // /workspace/<teamId>/<fileId> or /view/<fileId>: the file id is the last
    // UUID in the path portion (before any query string).
    elseif (preg_match('#/(?:workspace|view|dashboard/files)/(?:' . $uuid . '/)?(' . $uuid . ')#', $url, $m)) {
      $out['file_id'] = strtolower($m[1]);
    }
    elseif (preg_match('#^(' . $uuid . ')$#', $url, $m)) {
      // A bare file id was passed.
      $out['file_id'] = strtolower($m[1]);
    }

    if (preg_match('#[?&]page[-_]id=(' . $uuid . ')#', $url, $m)) {
      $out['page_id'] = strtolower($m[1]);
    }
    return $out;
  }

  /**
   * Returns the configured default Penpot file id.
   */
  public function getDefaultFileId(): string {
    return (string) $this->configFactory
      ->get('ai_penpot.settings')
      ->get('default_file_id');
  }

  /**
   * Resolves a safe Penpot base URL (no trailing slash).
   *
   * The base is admin-configurable, and every request sends the secret token in
   * an Authorization header - so a hostile or mistyped base could exfiltrate
   * the token or trigger SSRF. Defense in depth: only an http(s) URL with a
   * host is accepted; anything else (file://, gopher://, no host, ...) is
   * rejected and logged, and the request is refused.
   *
   * @return string
   *   A validated base URL with no trailing slash, or '' when unset/invalid.
   */
  public function getBaseUrl(): string {
    $configured = trim((string) ($this->configFactory
      ->get('ai_penpot.settings')
      ->get('penpot_base_url') ?: ''));
    if ($configured === '') {
      return '';
    }
    $parts = parse_url($configured);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], TRUE) || empty($parts['host'])) {
      $this->loggerFactory->get('ai_penpot')->warning(
        'Ignoring invalid penpot_base_url @base (must be an http(s) URL).',
        ['@base' => $configured],
      );
      return '';
    }
    return rtrim($configured, '/');
  }

  /**
   * Resolves the Penpot token, or throws a helpful exception when none is set.
   *
   * @return string
   *   The resolved access token (never empty).
   *
   * @throws \RuntimeException
   *   When no token is configured.
   */
  protected function requireToken(): string {
    $token = $this->getToken();
    if ($token === '') {
      throw new \RuntimeException('No Penpot access token configured. Add your Penpot access token to the Penpot Key at /admin/config/system/keys, then select it at /admin/config/ai/penpot.');
    }
    return $token;
  }

  /**
   * Resolves the Penpot base URL, or throws when none is configured.
   *
   * @return string
   *   The validated base URL.
   *
   * @throws \RuntimeException
   *   When no base URL is configured.
   */
  protected function requireBaseUrl(): string {
    $base = $this->getBaseUrl();
    if ($base === '') {
      throw new \RuntimeException('No Penpot instance URL configured. Set the base URL of your Penpot instance at /admin/config/ai/penpot.');
    }
    return $base;
  }

  /**
   * Calls a Penpot RPC command (POST JSON) and decodes the JSON response.
   *
   * Centralizes the token header, the RPC path, timeout, error handling and
   * JSON decoding shared by every Penpot endpoint call. Penpot content-
   * negotiates: the Accept: application/json header makes it answer with plain
   * JSON instead of its default Transit encoding.
   *
   * @param string $command
   *   The RPC command name, e.g. "get-file" or "get-page".
   * @param array $payload
   *   The command parameters.
   * @param string $context
   *   A short human label used in the error message on failure.
   * @param int $timeout
   *   Request timeout in seconds.
   *
   * @return array
   *   The decoded JSON response.
   *
   * @throws \RuntimeException
   *   When no token/base is configured, the request fails, or the body is not
   *   JSON.
   */
  protected function rpc(string $command, array $payload = [], string $context = 'Penpot API request failed', int $timeout = 30): array {
    $base = $this->requireBaseUrl();
    $token = $this->requireToken();
    $url = $base . '/api/rpc/command/' . rawurlencode($command);
    try {
      $response = $this->httpClient->request('POST', $url, [
        'headers' => [
          'Authorization' => 'Token ' . $token,
          'Accept' => 'application/json',
          'Content-Type' => 'application/json',
        ],
        'body' => json_encode($payload, JSON_THROW_ON_ERROR),
        'timeout' => $timeout,
      ]);
    }
    catch (\Throwable $e) {
      throw $this->requestFailed($context, $e);
    }
    $data = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($data)) {
      throw new \RuntimeException('Penpot API returned an unparseable response.');
    }
    return $data;
  }

  /**
   * Logs a failed Penpot request and returns a concise exception for it.
   *
   * Guzzle's own exception message embeds the whole request line and response
   * body, which is noisy in the UI and the logs. When the failure carries an
   * HTTP response, prefer the status code plus Penpot's own "hint"/"code"
   * field (e.g. "401 authentication-required"); otherwise fall back to the raw
   * message.
   *
   * @param string $context
   *   A short human label for the operation.
   * @param \Throwable $e
   *   The caught exception.
   *
   * @return \RuntimeException
   *   The exception to throw to the caller.
   */
  protected function requestFailed(string $context, \Throwable $e): \RuntimeException {
    $detail = $e->getMessage();
    if ($e instanceof RequestException && $e->hasResponse()) {
      $response = $e->getResponse();
      $status = $response->getStatusCode();
      $body = json_decode((string) $response->getBody(), TRUE);
      $penpot = is_array($body) ? trim((string) ($body['hint'] ?? $body['code'] ?? '')) : '';
      $detail = $penpot !== '' ? $status . ' ' . $penpot : 'HTTP ' . $status;
    }
    $this->loggerFactory->get('ai_penpot')
      ->error('@context: @detail', ['@context' => $context, '@detail' => $detail]);
    return new \RuntimeException($context . ': ' . $detail);
  }

  /**
   * Fetches a Penpot file's metadata (name + page index, no page objects).
   *
   * Penpot stores page objects behind pointers, so this response carries the
   * page ids and names but not their shapes; use fetchPage() for a page's
   * objects.
   *
   * @param string $file_id
   *   The Penpot file id (UUID).
   *
   * @return array
   *   Decoded JSON from get-file.
   *
   * @throws \RuntimeException
   *   When no token/base is configured or the request fails.
   */
  public function fetchFile(string $file_id): array {
    return $this->rpc('get-file', ['id' => trim($file_id)], 'Penpot file request failed', 60);
  }

  /**
   * Fetches a single page's shapes from a Penpot file.
   *
   * @param string $file_id
   *   The Penpot file id (UUID).
   * @param string $page_id
   *   The page id (UUID). Empty resolves to the file's first page.
   *
   * @return array
   *   Decoded JSON from get-page: ['id', 'name', 'objects' => [id => shape]].
   *
   * @throws \RuntimeException
   *   When no token/base is configured or the request fails.
   */
  public function fetchPage(string $file_id, string $page_id = ''): array {
    $file_id = trim($file_id);
    $page_id = trim($page_id);

    // A page id must be a UUID. Callers (e.g. an AI agent) may pass a page
    // *name* instead - resolve it. Empty falls back to the file's first page.
    if ($page_id !== '' && !preg_match('#^' . self::UUID . '$#', $page_id)) {
      $page_id = $this->resolvePageIdByName($file_id, $page_id);
    }
    if ($page_id === '') {
      $pages = $this->listDesignPages($file_id);
      $page_id = (string) ($pages[0]['page_id'] ?? '');
    }
    if ($page_id === '') {
      throw new \RuntimeException('No Penpot page id could be resolved for file ' . $file_id . '.');
    }
    return $this->rpc('get-page', [
      'file-id' => $file_id,
      'page-id' => $page_id,
    ], 'Penpot page request failed', 60);
  }

  /**
   * Lists a Penpot file's pages in document order.
   *
   * @param string $file_id
   *   The Penpot file id (UUID).
   *
   * @return array
   *   List of ['page_id' => string, 'name' => string] in order.
   */
  public function listDesignPages(string $file_id): array {
    $data = $this->fetchFile($file_id);
    $file_data = $data['data'] ?? [];
    // Penpot's JSON serialization camelCases keys (pagesIndex); Transit form
    // uses pages-index. Accept either.
    $index = $file_data['pagesIndex'] ?? $file_data['pages-index'] ?? [];
    $order = $file_data['pages'] ?? array_keys($index);
    $pages = [];
    foreach ($order as $pid) {
      $pid = (string) $pid;
      if ($pid === '') {
        continue;
      }
      $pages[] = [
        'page_id' => $pid,
        'name' => trim((string) ($index[$pid]['name'] ?? '')),
      ];
    }
    return $pages;
  }

  /**
   * Resolves a page name to its page id (case-insensitive).
   *
   * @param string $file_id
   *   The Penpot file id.
   * @param string $name
   *   The page name to look for.
   *
   * @return string
   *   The page id, or '' when no page carries that name.
   */
  public function resolvePageIdByName(string $file_id, string $name): string {
    $needle = mb_strtolower(trim($name));
    if ($needle === '') {
      return '';
    }
    foreach ($this->listDesignPages($file_id) as $page) {
      if (mb_strtolower($page['name']) === $needle) {
        return $page['page_id'];
      }
    }
    $this->loggerFactory->get('ai_penpot')
      ->warning('No Penpot page named "@name" found in file @id; falling back to the first page.', [
        '@name' => $name,
        '@id' => $file_id,
      ]);
    return '';
  }

  /**
   * Walks a page's shapes and extracts design tokens into a flat summary.
   *
   * Penpot's get-page returns a flat map of shapes (keyed by id, linked by
   * parentId). Fill colours are already hex strings, so - unlike Figma - no
   * float-to-hex conversion is needed.
   *
   * @param array $page
   *   Response from fetchPage(): ['name', 'objects' => [id => shape]].
   *
   * @return array
   *   Structured summary: colors, typography, texts, outline, root_name.
   */
  public function summarizeTokens(array $page): array {
    $colors = [];
    $typography = [];
    $texts = [];
    $outline = [];
    $objects = $page['objects'] ?? [];
    $root_name = trim((string) ($page['name'] ?? ''));

    foreach ($objects as $shape) {
      if (!is_array($shape)) {
        continue;
      }
      $name = (string) ($shape['name'] ?? '');
      $type = (string) ($shape['type'] ?? '');

      // Named shapes form the page outline (skip the auto root frame).
      if ($name !== '' && $name !== 'Root Frame') {
        $outline[] = $type . ': ' . $name;
      }

      // Solid fills → colours (fillColor is already a hex string).
      foreach (($shape['fills'] ?? []) as $fill) {
        $hex = trim((string) ($fill['fillColor'] ?? ''));
        if ($hex !== '') {
          $opacity = (float) ($fill['fillOpacity'] ?? 1.0);
          $label = $hex . ($opacity < 1.0 ? sprintf(' (%.0f%%)', $opacity * 100) : '');
          $colors[$label] = $colors[$label] ?? ($name !== '' ? $name : $type);
        }
      }

      // Text shape → typography + the real text content.
      if (strtolower($type) === 'text') {
        $chars = self::extractText($shape['content'] ?? []);
        $style = self::firstTextStyle($shape['content'] ?? []);
        if ($style !== NULL) {
          $key = ($style['fontFamily'] ?? '?') . ' ' . ($style['fontWeight'] ?? '') . ' ' . ($style['fontSize'] ?? '') . 'px';
          $typography[$key] = $typography[$key] ?? ($name !== '' ? $name : 'text');
        }
        if ($chars !== '') {
          $texts[] = [
            'text' => $chars,
            'name' => $name,
            'size' => (float) ($style['fontSize'] ?? 0),
          ];
        }
      }
    }

    return [
      'colors' => $colors,
      'typography' => $typography,
      'outline' => array_slice(array_values(array_unique($outline)), 0, 200),
      'texts' => $texts,
      'root_name' => $root_name,
    ];
  }

  /**
   * Concatenates all text runs of a Penpot text content tree.
   *
   * @param array $content
   *   The shape's `content` tree (root → paragraph-set → paragraph → runs).
   *
   * @return string
   *   The joined text, paragraphs separated by newlines.
   */
  public static function extractText(array $content): string {
    // Collect each paragraph's text as one line; a run's text is appended to
    // the paragraph currently being built.
    $lines = [];
    $collectRuns = static function (array $node, callable $self): string {
      $text = '';
      if (isset($node['text']) && is_string($node['text'])) {
        $text .= $node['text'];
      }
      foreach (($node['children'] ?? []) as $child) {
        if (is_array($child)) {
          $text .= $self($child, $self);
        }
      }
      return $text;
    };
    $walk = function (array $node) use (&$walk, &$lines, $collectRuns): void {
      foreach (($node['children'] ?? []) as $child) {
        if (!is_array($child)) {
          continue;
        }
        if (($child['type'] ?? '') === 'paragraph') {
          $line = trim($collectRuns($child, $collectRuns));
          if ($line !== '') {
            $lines[] = $line;
          }
          continue;
        }
        $walk($child);
      }
    };
    $walk($content);
    // Fallback: no paragraph nodes were found but text runs existed.
    if (!$lines) {
      $flat = trim($collectRuns($content, $collectRuns));
      if ($flat !== '') {
        $lines[] = $flat;
      }
    }
    return implode("\n", $lines);
  }

  /**
   * Returns the first text run's style properties from a content tree.
   *
   * @param array $content
   *   The shape's `content` tree.
   *
   * @return array|null
   *   The first run's style keys (fontFamily, fontWeight, fontSize), or NULL.
   */
  public static function firstTextStyle(array $content): ?array {
    $found = NULL;
    $walk = function (array $node) use (&$walk, &$found): void {
      if ($found !== NULL) {
        return;
      }
      if (isset($node['text']) && is_string($node['text'])) {
        $found = $node;
        return;
      }
      foreach (($node['children'] ?? []) as $child) {
        if (is_array($child)) {
          $walk($child);
        }
      }
    };
    $walk($content);
    return $found;
  }

}
