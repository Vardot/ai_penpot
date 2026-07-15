<?php

declare(strict_types=1);

namespace Drupal\ai_penpot\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_penpot\PenpotContextClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures the AI Penpot module: the instance URL, token and default file.
 */
class SettingsForm extends ConfigFormBase {

  private const SETTINGS = 'ai_penpot.settings';

  /**
   * The Penpot context client.
   */
  protected PenpotContextClient $penpotClient;

  /**
   * The Key module repository, when available.
   *
   * @var object|null
   */
  protected $keyRepository = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->penpotClient = $container->get('ai_penpot.client');
    $instance->keyRepository = $container->has('key.repository')
      ? $container->get('key.repository')
      : NULL;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_penpot_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::SETTINGS);

    // --- Penpot connection (instance URL + token + default file) ----------
    $form['connection'] = [
      '#type' => 'details',
      '#title' => $this->t('Penpot connection'),
      '#open' => TRUE,
      '#description' => $this->t('How the module authenticates to the Penpot RPC API and which file it reads by default.'),
    ];
    $form['connection']['penpot_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Penpot instance URL'),
      '#default_value' => $config->get('penpot_base_url') ?: '',
      '#placeholder' => 'https://design.penpot.app',
      '#description' => $this->t('The base URL of your Penpot instance. For Penpot Cloud use <code>https://design.penpot.app</code>; for a self-hosted instance use its address (e.g. <code>https://penpot.example.com</code>). The module calls <code>&lt;URL&gt;/api/rpc/command/…</code>.'),
    ];
    // Only offer authentication keys. The token chosen here is sent verbatim to
    // the Penpot API in the Authorization header, so listing keys would let
    // an admin pick (and transmit) an unrelated secret - e.g. the site's
    // easy_encryption private key - to a third party. Restricting the options
    // also means Drupal's own select validation rejects any tampered POST that
    // names a non-authentication key.
    $key_options = ['' => $this->t('- Select -')];
    $has_keys = FALSE;
    if ($this->keyRepository) {
      foreach ($this->keyRepository->getKeysByType('authentication') as $key) {
        $key_options[$key->id()] = $key->label();
        $has_keys = TRUE;
      }
    }
    $form['connection']['penpot_token_key'] = [
      '#type' => 'select',
      '#title' => $this->t('Penpot access token (Key)'),
      '#options' => $key_options,
      '#default_value' => $config->get('penpot_token_key') ?: '',
      '#description' => $has_keys
        ? $this->t('The Key that holds your Penpot access token. Create one in Penpot (<em>Your account → Access tokens</em>), then store it in the <a href=":keys">Penpot access token Key</a> (kept encrypted at rest via easy_encryption, the same way Drupal AI stores its provider keys). Self-hosted instances must have access tokens enabled (<code>enable-access-tokens</code> flag).', [
          ':keys' => '/admin/config/system/keys',
        ])
        : $this->t('No keys defined yet. Add one at <a href=":url">Configuration → System → Keys</a> holding your Penpot access token.', [':url' => '/admin/config/system/keys']),
    ];
    $form['connection']['default_file_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Penpot file id'),
      '#default_value' => $config->get('default_file_id') ?: '',
      '#description' => $this->t('Used when a tool is called without a file id. This is the UUID in a <code>penpot.app/#/workspace/…/&lt;fileId&gt;</code> or <code>/view/&lt;fileId&gt;</code> URL. Leave empty to require an explicit file id or link each time.'),
    ];

    // --- Test connection --------------------------------------------------
    $form['test'] = [
      '#type' => 'details',
      '#title' => $this->t('Test connection'),
      '#open' => TRUE,
      '#description' => $this->t('Probes the Penpot API with the <em>saved</em> URL, token and default file id. Save your changes first if you just picked a different Key.'),
    ];
    $form['test']['test_connection'] = [
      '#type' => 'submit',
      '#value' => $this->t('Test Penpot connection'),
      '#submit' => ['::testConnection'],
      '#limit_validation_errors' => [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    // The token is sent to this base on every request, so reject anything that
    // is not a plain http(s) URL (no file://, no scheme-less host) up front.
    $base = trim((string) $form_state->getValue('penpot_base_url'));
    if ($base !== '') {
      $parts = parse_url($base);
      $scheme = strtolower((string) ($parts['scheme'] ?? ''));
      if (!in_array($scheme, ['http', 'https'], TRUE) || empty($parts['host'])) {
        $form_state->setErrorByName('penpot_base_url', $this->t('The Penpot instance URL must be a full http(s) URL, e.g. <code>https://design.penpot.app</code>.'));
      }
    }
  }

  /**
   * Submit handler: probes the Penpot API with current configuration.
   */
  public function testConnection(array &$form, FormStateInterface $form_state): void {
    $client = $this->penpotClient;
    $file_id = $form_state->getValue('default_file_id') ?: $client->getDefaultFileId();

    if ($client->getBaseUrl() === '') {
      $this->messenger()->addError($this->t('No Penpot instance URL saved. Set and save the instance URL first.'));
      return;
    }
    if ($client->getToken() === '') {
      $this->messenger()->addError($this->t('No Penpot token resolved. Pick a Key holding your Penpot access token first, then save.'));
      return;
    }
    if ($file_id === '') {
      $this->messenger()->addError($this->t('Set a default Penpot file id to test against.'));
      return;
    }
    try {
      $pages = $client->listDesignPages($file_id);
      $page = $client->fetchPage($file_id);
      $summary = $client->summarizeTokens($page);
      $this->messenger()->addStatus($this->t('Connected to Penpot file %key: %pages pages. First page %page has %colors colours and %type typography styles.', [
        '%key' => $file_id,
        '%pages' => count($pages),
        '%page' => $summary['root_name'] !== '' ? $summary['root_name'] : ($pages[0]['name'] ?? '?'),
        '%colors' => count($summary['colors']),
        '%type' => count($summary['typography']),
      ]));
    }
    catch (\Throwable $e) {
      $this->getLogger('ai_penpot')->error('Penpot connection test failed (file @key): @msg', [
        '@key' => $file_id,
        '@msg' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('Penpot connection failed: @msg', ['@msg' => $e->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(self::SETTINGS)
      // The token always comes through the Key module.
      ->set('penpot_token_source', 'key')
      ->set('penpot_token_key', $form_state->getValue('penpot_token_key'))
      ->set('penpot_base_url', rtrim(trim((string) $form_state->getValue('penpot_base_url')), '/'))
      ->set('default_file_id', trim((string) $form_state->getValue('default_file_id')))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
