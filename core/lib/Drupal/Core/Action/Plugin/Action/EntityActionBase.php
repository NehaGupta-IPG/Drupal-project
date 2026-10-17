<?php

namespace Drupal\Core\Action\Plugin\Action;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Base class for entity-based actions.
 */
abstract class EntityActionBase extends ActionBase implements DependentPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Constructs an EntityActionBase object.
   *
   * @param mixed[] $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ?LoggerChannelFactoryInterface $logger_factory = NULL) {
    if (!$logger_factory instanceof LoggerChannelFactoryInterface) {
      @trigger_error('Calling ' . __CLASS__ . '::__construct() without the $logger_factory argument is deprecated in drupal:11.2.0 and will be required in drupal:12.0.0. See https://www.drupal.org/node/2555609', E_USER_DEPRECATED);
      $logger_factory = \Drupal::service('logger.factory');
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Logs a publish/unpublish state change for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose state changed.
   * @param string $message
   *   The full log message template (e.g. '@type: published %title.').
   */
  protected function logStateChange(EntityInterface $entity, string $message): void {
    $channel = $entity->getEntityType()->getProvider();
    $this->loggerFactory->get($channel)->info($message, [
      '@type'  => $entity->bundle(),
      '%title' => $entity->label() ?? '',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $module_name = $this->entityTypeManager
      ->getDefinition($this->getPluginDefinition()['type'])
      ->getProvider();
    return ['module' => [$module_name]];
  }

}
