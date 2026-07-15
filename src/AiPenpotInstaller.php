<?php

declare(strict_types=1);

namespace Drupal\ai_penpot;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Install/runtime wiring for the AI Penpot connection module.
 *
 * The module ships the Penpot connection (client + settings + the `penpot`
 * Key). This installer provisions that Key on install, and exposes
 * seedContextItems() as a public helper that seeds the editable Penpot AI
 * guidance into AI Context items when the ai_context module is present.
 */
final class AiPenpotInstaller {

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly LoggerChannelFactoryInterface $loggerFactory,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly PluginManagerInterface $keyProviderManager,
  ) {}

  /**
   * Creates the `penpot` Key the module reads its token from (empty value).
   */
  public function installPenpotKey(): void {
    $config = $this->configFactory->getEditable('ai_penpot.settings');
    $key_id = (string) ($config->get('penpot_token_key') ?: 'penpot');
    try {
      $storage = $this->entityTypeManager->getStorage('key');
      if (!$storage->load($key_id)) {
        $provider = $this->keyProviderAvailable('easy_encrypted') ? 'easy_encrypted' : 'config';
        $storage->create([
          'id' => $key_id,
          'dependencies' => ['enforced' => ['module' => ['ai_penpot']]],
          'label' => 'Penpot access token',
          'description' => 'Penpot access token used by AI Penpot. Create one at your Penpot instance (Your account → Access tokens) and paste it here; it is kept in the Key module (encrypted at rest when easy_encryption is enabled).',
          'key_type' => 'authentication',
          'key_type_settings' => [],
          'key_provider' => $provider,
          'key_provider_settings' => $provider === 'config' ? ['base64_encoded' => FALSE] : [],
          'key_input' => 'text_field',
          'key_input_settings' => ['base64_encoded' => FALSE],
        ])->save();
      }
      $config->set('penpot_token_source', 'key')->set('penpot_token_key', $key_id)->save();
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('ai_penpot')->warning('Could not auto-create the Penpot Key on install: @msg', ['@msg' => $e->getMessage()]);
    }
  }

  /**
   * Whether a Key provider plugin id is available on this site.
   */
  public function keyProviderAvailable(string $plugin_id): bool {
    try {
      return $this->keyProviderManager->hasDefinition($plugin_id);
    }
    catch (\Throwable) {
      return FALSE;
    }
  }

  /**
   * Seeds (or updates) the editable Penpot AI Context items from config.
   *
   * The rule TEXT lives in config (ai_penpot.settings), not in PHP. No-op when
   * ai_context is absent. Items with empty content are skipped.
   *
   * @param bool $update
   *   When TRUE, overwrite the content + scope of an existing item; when FALSE,
   *   only create missing items (leave author edits intact).
   */
  public function seedContextItems(bool $update = FALSE): void {
    // During a recipe apply, ai_context can be installed earlier in the same
    // batch; refresh the entity-type definitions so ai_context_item is visible
    // (a stale cache would make hasDefinition() return FALSE and skip seeding).
    $this->entityTypeManager->clearCachedDefinitions();
    if (!$this->moduleHandler->moduleExists('ai_context') || !$this->entityTypeManager->hasDefinition('ai_context_item')) {
      return;
    }
    // Reset the cached config so a build_rules value written earlier in the
    // same request (e.g. by a setup routine during a recipe apply) is read
    // fresh; otherwise the items can be skipped as "empty" and never seed.
    $this->configFactory->reset('ai_penpot.settings');
    $config = $this->configFactory->get('ai_penpot.settings');
    $scope = (array) ($config->get('context_scope') ?: ['global' => ['global']]);
    $items = [
      'Penpot Build Rules' => [
        'description' => 'Rules the Penpot design-context tool and Canvas AI agents follow when building pages and components from a Penpot design.',
        'purpose' => 'Read by Penpot-aware AI agents as the build instruction for Penpot-to-Canvas builds. Edit here to change how designs are built.',
        'content' => (string) $config->get('build_rules'),
      ],
      'Penpot Accessibility Rules' => [
        'description' => 'WCAG 2.1 AA accessibility rules for everything built from a Penpot design.',
        'purpose' => 'Read by Penpot-aware AI agents as the accessibility instruction for Penpot-to-Canvas builds.',
        'content' => (string) $config->get('accessibility_rules'),
      ],
      'Penpot Component Mapping Governance' => [
        'description' => 'Governs how the AI maps a Penpot design onto existing components: reuse/extend before creating, never create without approval, report design-vs-theme differences.',
        'purpose' => 'Delivered to the Canvas AI agents so component-mapping governance is applied site-wide. Optional - present only when a setup provides the text.',
        'content' => (string) $config->get('mapping_governance'),
      ],
    ];
    try {
      $storage = $this->entityTypeManager->getStorage('ai_context_item');
      foreach ($items as $label => $fields) {
        if ($fields['content'] === '') {
          continue;
        }
        $existing = $storage->loadByProperties(['label' => $label]);
        $entity = $existing ? reset($existing) : NULL;
        if ($entity && !$update) {
          continue;
        }
        if ($entity) {
          $entity->set('content', ['value' => $fields['content'], 'format' => 'plain_text']);
          $entity->set('scope', $scope);
          $entity->save();
          continue;
        }
        $storage->create([
          'type' => 'default',
          'status' => TRUE,
          'uid' => 1,
          'label' => $label,
          'description' => ['value' => $fields['description'], 'format' => 'plain_text'],
          'purpose' => ['value' => $fields['purpose'], 'format' => 'plain_text'],
          'content' => ['value' => $fields['content'], 'format' => 'plain_text'],
          'scope' => $scope,
        ])->save();
      }
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('ai_penpot')->warning('Could not seed AI Context items: @msg', ['@msg' => $e->getMessage()]);
    }
  }

}
