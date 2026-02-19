<?php

namespace Drupal\workflows;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Workflow entities.
 */
class WorkflowListBuilder extends ConfigEntityListBuilder {

  /**
   * The workflow type plugin manager.
   *
   * @var PluginManagerInterface
   */
  protected $workflowTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('plugin.manager.workflows.type')
    );
  }

  /**
   * Constructs a new WorkflowListBuilder object.
   *
   * @param EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param EntityStorageInterface $storage
   *   The entity storage class.
   * @param PluginManagerInterface $workflow_type_manager
   *   The workflow type plugin manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, PluginManagerInterface $workflow_type_manager) {
    parent::__construct($entity_type, $storage);
    $this->workflowTypeManager = $workflow_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workflow_admin_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Workflow');
    $header['type'] = $this->t('Type');
    $header['states'] = $this->t('States');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var WorkflowInterface $entity */
    $row['label'] = $entity->label();

    $row['type']['data'] = [
      '#markup' => $entity->getTypePlugin()->label(),
    ];

    $items = array_map([State::class, 'labelCallback'], $entity->getTypePlugin()->getStates());
    $row['states']['data'] = [
      '#theme' => 'item_list',
      '#context' => ['list_style' => 'comma-list'],
      '#items' => $items,
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $workflow_types_count = count($this->workflowTypeManager->getDefinitions());
    if ($workflow_types_count === 0) {
      $build['table']['#empty'] = $this->t('There are no workflow types available. In order to create workflows you need to install a module that provides a workflow type. For example, the <a href=":content-moderation">Content Moderation</a> module provides a workflow type that enables workflows for content entities.', [':content-moderation' => Url::fromRoute('system.modules_list', [], ['fragment' => 'module-content-moderation'])->toString()]);
    }
    return $build;
  }

}
