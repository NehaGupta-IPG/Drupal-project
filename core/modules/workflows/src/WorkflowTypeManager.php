<?php

namespace Drupal\workflows;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\workflows\Attribute\WorkflowType;

/**
 * Provides a Workflow type plugin manager.
 *
 * @see \Drupal\workflows\Annotation\WorkflowType
 * @see \Drupal\workflows\WorkflowTypeInterface
 * @see plugin_api
 */
class WorkflowTypeManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/WorkflowType', $namespaces, $module_handler, WorkflowTypeInterface::class, WorkflowType::class, 'Drupal\workflows\Annotation\WorkflowType');
    $this->alterInfo('workflow_type_info');
    $this->setCacheBackend($cache_backend, 'workflow_type_info');
  }

}
