<?php

namespace Drupal\block\Plugin\migrate\process;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Determines the block plugin ID.
 */
#[MigrateProcess('block_plugin_id')]
class BlockPluginId extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a BlockPluginId object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface|null $blockContentStorage
   *   The block content storage object. NULL if the block_content module is
   *   not installed.
   * @param \Drupal\migrate\MigrateLookupInterface $migrateLookup
   *   The migrate lookup service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    protected ?EntityStorageInterface $blockContentStorage,
    protected MigrateLookupInterface $migrateLookup,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager->hasDefinition('block_content') ? $entity_type_manager->getStorage('block_content') : NULL,
      $container->get('migrate.lookup')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Set the block plugin id.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (is_array($value)) {
      [$module, $delta] = $value;
      switch ($module) {
        case 'aggregator':
          [$type] = explode('-', $delta);
          if ($type == 'feed') {
            return 'aggregator_feed_block';
          }
          break;

        case 'menu':
          return "system_menu_block:$delta";

        case 'block':
          if ($this->blockContentStorage) {
            $block_id = $row->getDestinationProperty('_block_module_plugin_id');
            // Legacy generated migrations will not have the destination
            // property '_block_module_plugin_id'.
            if (!$block_id) {
              $lookup_result = $this->migrateLookup->lookup(['d6_custom_block', 'd7_custom_block'], [$delta]);
              if ($lookup_result) {
                $block_id = $lookup_result[0]['id'];
              }
            }
            if ($block_id) {
              return 'block_content:' . $this->blockContentStorage->load($block_id)->uuid();
            }
          }
          break;

        default:
          break;
      }
    }
    else {
      return $value;
    }
  }

}
