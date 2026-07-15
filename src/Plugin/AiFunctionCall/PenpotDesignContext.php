<?php

declare(strict_types=1);

namespace Drupal\ai_penpot\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\ai_penpot\PenpotContextClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * AI Agent tool: read the design context of a Penpot page.
 *
 * Given a Penpot link (or file id + page), returns the page's design tokens -
 * colours, typography, the real text content and a shape outline - so the
 * Canvas AI assistant can build the section from the design, not guess.
 */
#[FunctionCall(
  id: 'ai_penpot:design_context',
  function_name: 'ai_penpot_design_context',
  name: 'Penpot: Read Design Context',
  description: 'Reads the design context of ONE Penpot page: its colours, typography, the real text content (verbatim) and a shape outline. Paste a Penpot link (or pass a file id and page id/name). Use this to build a section or page FROM a design: reuse existing theme components, map the colours onto the theme design tokens (not hard-coded hex), and fill components with the returned text.',
  group: 'information_tools',
  module_dependencies: ['ai_penpot'],
  context_definitions: [
    'penpot_url' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Penpot URL'),
      description: new TranslatableMarkup('A Penpot workspace/view link. The file id and (when present) the page id are extracted from it. Leave empty to use file_id/page and the configured default file.'),
      required: FALSE,
    ),
    'file_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Penpot file id'),
      description: new TranslatableMarkup('The Penpot file id (UUID). Leave empty to extract from penpot_url or use the configured default file.'),
      required: FALSE,
    ),
    'page' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Page id or name'),
      description: new TranslatableMarkup('The page to read - a page id (UUID) or a page name. Leave empty to use the page id from penpot_url, or the first page of the file.'),
      required: FALSE,
    ),
  ],
)]
class PenpotDesignContext extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The Penpot context client.
   */
  protected PenpotContextClient $penpotClient;

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The collected readable output.
   */
  protected string $result = '';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
      $container->get('plugin.manager.ai_data_type_converter'),
    );
    $instance->penpotClient = $container->get('ai_penpot.client');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    if (
      !$this->currentUser->hasPermission('use ai penpot design context')
      && !$this->currentUser->hasPermission('administer ai agents')
      && !$this->currentUser->hasPermission('use Drupal Canvas AI')
    ) {
      throw new \Exception('You do not have permission to read Penpot design context.');
    }

    $file_id = trim((string) $this->getContextValue('file_id'));
    $page = trim((string) $this->getContextValue('page'));
    $url = trim((string) $this->getContextValue('penpot_url'));
    if ($url !== '') {
      $parsed = PenpotContextClient::parsePenpotUrl($url);
      $file_id = $file_id !== '' ? $file_id : $parsed['file_id'];
      $page = $page !== '' ? $page : $parsed['page_id'];
    }
    if ($file_id === '') {
      $file_id = $this->penpotClient->getDefaultFileId();
    }
    if ($file_id === '') {
      $this->result = 'No Penpot file id given or configured.';
      return;
    }

    $page_data = $this->penpotClient->fetchPage($file_id, $page);
    $summary = $this->penpotClient->summarizeTokens($page_data);

    $this->result = Yaml::dump([
      'penpot_file_id' => $file_id,
      'page' => [
        'id' => (string) ($page_data['id'] ?? ''),
        'name' => $summary['root_name'],
      ],
      'colors' => $summary['colors'],
      'typography' => $summary['typography'],
      'content_texts' => $summary['texts'],
      'outline' => $summary['outline'],
      'hint' => 'Reuse existing theme components for these sections; map the colours onto the active theme design tokens (not hard-coded hex); use content_texts verbatim (larger sizes = headings, smaller = body/labels).',
    ], 6, 2);
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->result;
  }

}
