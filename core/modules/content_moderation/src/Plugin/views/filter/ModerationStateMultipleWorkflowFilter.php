<?php

namespace Drupal\content_moderation\Plugin\views\filter;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Drupal\views\ViewExecutable;
use Drupal\workflows\Entity\Workflow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters by moderation state across multiple workflows.
 *
 * Unlike core's moderation_state_filter which uses composite workflow-state
 * IDs (e.g. "editorial-draft"), this filter aggregates states across all
 * content moderation workflows using plain state IDs (e.g. "draft"). This is
 * less confusing for editors who don't know which workflow governs their
 * content.
 *
 * @see https://www.drupal.org/project/drupal/issues/3382759
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter("moderation_state_multiple_workflow_filter")]
class ModerationStateMultipleWorkflowFilter extends ManyToOne {

  use StringTranslationTrait;

  /**
   * The current display.
   *
   * @var string
   */
  protected $currentDisplay;

  /**
   * The views join plugin manager.
   */
  protected ViewsHandlerManager $joinManager;

  /**
   * Constructs a ModerationStateMultipleWorkflowFilter object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ViewsHandlerManager $join_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->joinManager = $join_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ModerationStateMultipleWorkflowFilter {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.views.join'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);
    $this->valueTitle = $this->t('Filter by workflow state');
    $this->definition['options callback'] = [$this, 'generateOptions'];
    $this->currentDisplay = $view->current_display;
  }

  /**
   * Generates the list of moderation state options across all workflows.
   *
   * @return array
   *   An array of state labels keyed by state ID.
   */
  public function generateOptions() {
    $workflows = Workflow::loadMultipleByType('content_moderation');
    $filter_items = [];
    foreach ($workflows as $workflow) {
      $states = $workflow->getTypePlugin()->getStates();
      foreach ($states as $state_id => $state) {
        // Merge labels if they diverge from the machine name.
        // Otherwise, two states with the same machine name but different label
        // will cause the last one listed to win.
        if (empty($filter_items[$state_id])) {
          $filter_items[$state_id] = $state->label();
        }
        elseif ($filter_items[$state_id] !== $state->label()) {
          $filter_items[$state_id] = "{$filter_items[$state_id]} | {$state->label()}";
        }
      }
    }
    return $filter_items;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    if (!empty($this->value)) {
      $configuration = [
        'table' => 'content_moderation_state_field_data',
        'field' => 'content_entity_revision_id',
        'left_table' => 'node_field_data',
        'left_field' => 'vid',
        'operator' => '=',
      ];
      $join = $this->joinManager->createInstance('standard', $configuration);
      $this->query->addRelationship('content_moderation_state_field_data', $join, 'node_field_data');
      $this->query->addWhere('AND', 'content_moderation_state_field_data.moderation_state', $this->value, 'IN');
      $this->query->addWhere('AND', 'content_moderation_state_field_data.content_entity_type_id', 'node', '=');
    }
  }

}
