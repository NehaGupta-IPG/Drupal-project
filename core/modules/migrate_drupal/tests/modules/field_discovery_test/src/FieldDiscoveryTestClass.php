<?php

declare(strict_types=1);

namespace Drupal\field_discovery_test;

use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_drupal\FieldDiscovery;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_drupal\Plugin\MigrateFieldPluginManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * A test class to expose protected methods.
 */
class FieldDiscoveryTestClass extends FieldDiscovery {

  /**
   * An array of test data.
   *
   * @var array
   */
  protected $testData;

  public function __construct(MigrateFieldPluginManagerInterface $field_plugin_manager, MigrationPluginManagerInterface $migration_plugin_manager, LoggerInterface $logger, array $test_data = []) {
    parent::__construct($field_plugin_manager, $migration_plugin_manager, $logger);
    $this->testData = $test_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllFields($core) {
    if (!empty($this->testData['getAllFields'][$core])) {
      return $this->testData['getAllFields'][$core];
    }
    return parent::getAllFields($core);
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleFields($core, $entity_type_id, $bundle) {
    return parent::getBundleFields($core, $entity_type_id, $bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFields($core, $entity_type_id) {
    return parent::getEntityFields($core, $entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldInstanceStubMigrationDefinition($core) {
    return parent::getFieldInstanceStubMigrationDefinition($core);
  }

  /**
   * {@inheritdoc}
   */
  public function getCoreVersion(MigrationInterface $migration) {
    return parent::getCoreVersion($migration);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldPlugin($field_type, MigrationInterface $migration) {
    return parent::getFieldPlugin($field_type, $migration);
  }

  /**
   * {@inheritdoc}
   */
  public function getSourcePlugin($core) {
    return parent::getSourcePlugin($core);
  }

}
