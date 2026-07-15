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
 * AI Agent tool: list the pages of a Penpot design file.
 *
 * The first step of a full-site build: returns every page with its id so the
 * agent can plan one Canvas page per design page.
 */
#[FunctionCall(
  id: 'ai_penpot:list_design_pages',
  function_name: 'ai_penpot_list_design_pages',
  name: 'Penpot: List Design Pages',
  description: 'Lists the pages of a Penpot design file - every page with its page id, in order. Use this FIRST when asked to build a full site from a Penpot file: plan one Canvas page per design page (skip style-guide/foundation pages such as Colors, Typography, Icons), then read the design context for each page to build it.',
  group: 'information_tools',
  module_dependencies: ['ai_penpot'],
  context_definitions: [
    'penpot_url' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Penpot URL'),
      description: new TranslatableMarkup('A Penpot link; the file id is extracted from it. Leave empty to use the configured default file.'),
      required: FALSE,
    ),
    'file_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Penpot file id'),
      description: new TranslatableMarkup('The Penpot file id (UUID). Leave empty to extract from penpot_url or use the configured default.'),
      required: FALSE,
    ),
  ],
)]
class PenpotListDesignPages extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
    $url = trim((string) $this->getContextValue('penpot_url'));
    if ($file_id === '' && $url !== '') {
      $file_id = PenpotContextClient::parsePenpotUrl($url)['file_id'];
    }
    if ($file_id === '') {
      $file_id = $this->penpotClient->getDefaultFileId();
    }
    if ($file_id === '') {
      $this->result = 'No Penpot file id given or configured.';
      return;
    }

    $pages = $this->penpotClient->listDesignPages($file_id);
    $this->result = Yaml::dump([
      'penpot_file_id' => $file_id,
      'design_pages' => $pages,
      'hint' => 'Each page is a design surface. Build a site page from a page_id: read the design context for that page, create the section components, and place them on a Canvas page. Skip foundation/icon/style pages (Colors, Typography, Icons, Spacing).',
    ], 4, 2);
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->result;
  }

}
