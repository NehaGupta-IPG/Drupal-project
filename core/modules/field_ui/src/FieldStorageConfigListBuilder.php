<?php

namespace Drupal\field_ui;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of fields.
 *
 * @see \Drupal\field\Entity\FieldStorageConfig
 * @see field_ui_entity_type_build()
 */
class FieldStorageConfigListBuilder extends ConfigEntityListBuilder implements FormInterface {

  /**
   * An array of information about field types.
   *
   * @var array
   */
  protected $fieldTypes;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * An array of entity bundle information.
   *
   * @var array
   */
  protected $bundles;

  /**
   * The field type manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;


  /**
   * The array of field storage configs.
   *
   * @var \Drupal\field\FieldStorageConfigInterface[]
   */
  protected array $fieldStorageConfigs;

  /**
   * The name of field type.
   *
   * @var string
   */
  protected string $fieldTypeFilter;

  /**
   * The name of entity type.
   *
   * @var string
   */
  protected string $entityTypeFilter;

  /**
   * Constructs a new FieldStorageConfigListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The 'field type' plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info_service
   *   The bundle info service.
   * @param \Drupal\Core\Entity\EntityTypeRepositoryInterface $entityTypeRepository
   *   The entity type repository.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, FieldTypePluginManagerInterface $field_type_manager, EntityTypeBundleInfoInterface $bundle_info_service, protected EntityTypeRepositoryInterface $entityTypeRepository, protected FormBuilderInterface $formBuilder) {
    parent::__construct($entity_type, $entity_type_manager->getStorage($entity_type->id()));

    $this->entityTypeManager = $entity_type_manager;
    $this->bundles = $bundle_info_service->getAllBundleInfo();
    $this->fieldTypeManager = $field_type_manager;
    $this->fieldTypes = $this->fieldTypeManager->getDefinitions();
    $this->limit = FALSE;
    $this->fieldStorageConfigs = $this->load();
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.repository'),
      $container->get('form_builder')
    );
  }

  /**
   * Loads entity IDs using a pager sorted by the entity id.
   *
   * @return string[]
   *   An array of entity IDs.
   */
  protected function getEntityIds(): array {
    $query = $this->getStorage()->getQuery()
      ->sort($this->entityType->getKey('id'));

    if ($this->fieldTypeFilter) {
      $query->condition('type', $this->fieldTypeFilter);
    }
    if ($this->entityTypeFilter) {
      $query->condition('entity_type', $this->entityTypeFilter);
    }

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = $this->formBuilder->getForm($this);
    $build['#attached']['library'][] = 'field_ui/drupal.field_ui';
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Field name');
    $header['entity_type'] = $this->t('Entity type');
    $header['type'] = [
      'data' => $this->t('Field type'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
    ];
    $header['usage'] = $this->t('Used in');
    $header['settings_summary'] = $this->t('Summary');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $field_storage) {
    if ($field_storage->isLocked()) {
      $row['class'] = ['menu-disabled'];
      $row['data']['id'] = $this->t('@field_name (Locked)', ['@field_name' => $field_storage->getName()]);
    }
    else {
      $row['data']['id'] = $field_storage->getName();
    }

    $entity_type_id = $field_storage->getTargetEntityTypeId();
    // Adding the entity type.
    $row['data']['entity_type'] = $entity_type_id;

    $field_type = $this->fieldTypes[$field_storage->getType()];
    $row['data']['type'] = $this->t('@type (module: @module)', [
      '@type' => $field_type['label'],
      '@module' => $field_type['provider'],
    ]);

    $usage = [];
    foreach ($field_storage->getBundles() as $bundle) {
      if ($route_info = FieldUI::getOverviewRouteInfo($entity_type_id, $bundle)) {
        $usage[] = Link::fromTextAndUrl($this->bundles[$entity_type_id][$bundle]['label'], $route_info)->toRenderable();
      }
      else {
        $usage[] = $this->bundles[$entity_type_id][$bundle]['label'];
      }
    }
    $row['data']['usage']['data'] = [
      '#theme' => 'item_list',
      '#items' => $usage,
      '#context' => ['list_style' => 'comma-list'],
    ];
    $summary = $this->fieldTypeManager->getStorageSettingsSummary($field_storage);
    $row['data']['settings_summary'] = empty($summary) ? '' : [
      'data' => [
        '#theme' => 'item_list',
        '#items' => $summary,
      ],
      'class' => ['storage-settings-summary-cell'],
    ];
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form--inline', 'clearfix']],
    ];

    $form['filters']['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#options' => $this->entityTypeOptions($this->fieldStorageConfigs),
      '#empty_option' => $this->t('- Select an entity type -'),
    ];

    $form['filters']['field_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Field type'),
      '#options' => $this->fieldTypeOptions($this->fieldStorageConfigs),
      '#empty_option' => $this->t('- Select a field type -'),
    ];

    $form['filters']['actions']['#type'] = 'actions';
    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#button_type' => 'secondary',
    ];

    $form['table_container'] = parent::render();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // No validation.
  }

  /**
   * Build entity types options for select list.
   *
   * @param \Drupal\field\FieldStorageConfigInterface[] $fieldStorageConfigs
   *   The array of field storage configs.
   *
   * @return string[]
   *   The array of options.
   */
  protected function entityTypeOptions(array $fieldStorageConfigs): array {
    $entityLabels = $this->entityTypeRepository->getEntityTypeLabels();

    // Gather valid entity types.
    $entityTypeOptions = [];
    foreach ($fieldStorageConfigs as $fieldStorageConfig) {
      $entityName = $fieldStorageConfig->getTargetEntityTypeId();
      if (array_key_exists($entityName, $entityLabels)) {
        if (isset($entityTypeOptions[$entityName])) {
          continue;
        }

        $entityLabel = $entityLabels[$entityName]->render();
        $entityTypeOptions[$entityName] = "$entityLabel ($entityName)";
      }
    }

    return $entityTypeOptions;
  }

  /**
   * Build field types options for select list.
   *
   * @param \Drupal\field\FieldStorageConfigInterface[] $fieldStorageConfigs
   *   The array of field storage configs.
   *
   * @return string[]
   *   The array of options.
   */
  protected function fieldTypeOptions(array $fieldStorageConfigs): array {
    $existed_field_types = [];
    foreach ($fieldStorageConfigs as $fieldStorageConfig) {
      $existed_field_types[] = $fieldStorageConfig->getType();
    }

    // Gather valid field types.
    $fieldTypeOptions = [];
    foreach ($this->fieldTypeManager->getGroupedDefinitions($this->fieldTypeManager->getUiDefinitions()) as $category => $field_types) {
      foreach ($field_types as $name => $field_type) {
        if (in_array($name, $existed_field_types)) {
          $fieldTypeOptions[$category][$name] = $field_type['label'];
        }
      }
    }

    return $fieldTypeOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $formValues = $form_state->getValues();

    $this->fieldTypeFilter = $formValues['field_type'];
    $this->entityTypeFilter = $formValues['entity_type'];

    $form_state->setRebuild();
  }

  public function getFormId(): string {
    return 'field_storage_config_form';
  }

}
