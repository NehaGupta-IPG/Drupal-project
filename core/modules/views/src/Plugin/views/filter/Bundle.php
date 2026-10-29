<?php

namespace Drupal\views\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;

/**
 * Filter class which allows filtering by entity bundles.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter("bundle")]
class Bundle extends InOperator {

  /**
   * The entity type for the filter.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The entity type definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfoService;

  /**
   * The bundle key.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  public string $real_field;

  /**
   * Constructs a Bundle object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info_service
   *   The bundle info service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $bundle_info_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->bundleInfoService = $bundle_info_service;
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->entityTypeId = $this->getEntityType();
    $this->entityType = \Drupal::entityTypeManager()->getDefinition($this->entityTypeId);
    $this->real_field = $this->entityType->getKey('bundle');
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $types = $this->bundleInfoService->getBundleInfo($this->entityTypeId);
      $this->valueTitle = $this->t('@entity types', ['@entity' => $this->entityType->getLabel()]);

      $options = [];
      foreach ($types as $type => $info) {
        $options[$type] = $info['label'];
      }

      // Remove unavailable options.
      $options = array_diff_key($options, $this->unavailableOptions());

      asort($options);
      $this->valueOptions = $options;
    }

    return $this->valueOptions;
  }

  /**
   * Get unavailable options depending on the access.
   *
   * @return array
   *   The unavailable options.
   */
  protected function unavailableOptions(): array {
    $types = $this->bundleInfoService->getBundleInfo($this->entityTypeId);
    $bundle_type = $this->entityType->getBundleEntityType();
    $should_filter = $bundle_type !== NULL && ($this->isExposed() || !empty($this->options['bundle_access']));
    $bundle_storage = $should_filter ? $this->entityTypeManager->getStorage($bundle_type) : NULL;
    $options = [];

    foreach ($types as $type => $info) {
      if ($should_filter) {
        $bundle_entity = $bundle_storage->load($type);
        if (!$bundle_entity || !$bundle_entity->access('view label')) {
          $options[$type] = $info['label'];
        }
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['bundle_access'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['bundle_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check access'),
      '#description' => $this->t('If checked, filter and content will take care of bundle access.'),
      // Safety.
      '#default_value' => !empty($this->options['bundle_access']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Ensure we filter on allowed options.
    // In case of 'not in' operation, not allowed options have to be explicitly
    // added.
    if ('not in' === $this->operator) {
      $unavailableOptionsKey = array_keys($this->unavailableOptions());
      $unavailableOptions = array_combine($unavailableOptionsKey, $unavailableOptionsKey);
      $this->value = array_merge($this->value, $unavailableOptions);
    }
    // In case of 'in' operation, not allowed options have to be removed.
    else {
      // Keep only values of $this->value which are keys in $this->getValueOptions().
      $options = $this->getValueOptions();
      $this->value = array_filter($this->value, function($value) use ($options) {
        return array_key_exists($value, $options);
      });

    }

    // Make sure that the entity base table is in the query.
    $this->ensureMyTable();
    parent::query();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    $bundle_entity_type = $this->entityType->getBundleEntityType();
    $bundle_entity_storage = $this->entityTypeManager->getStorage($bundle_entity_type);

    foreach (array_keys($this->value) as $bundle) {
      if ($bundle_entity = $bundle_entity_storage->load($bundle)) {
        $dependencies[$bundle_entity->getConfigDependencyKey()][] = $bundle_entity->getConfigDependencyName();
      }
    }

    return $dependencies;
  }

}
