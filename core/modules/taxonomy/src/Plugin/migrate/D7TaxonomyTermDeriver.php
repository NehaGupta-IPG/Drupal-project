<?php

namespace Drupal\taxonomy\Plugin\migrate;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\Plugin\MigrationDeriverTrait;
use Drupal\migrate_drupal\FieldDiscoveryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver for Drupal 7 taxonomy term migrations based on vocabularies.
 */
class D7TaxonomyTermDeriver extends DeriverBase implements ContainerDeriverInterface {
  use MigrationDeriverTrait;
  use StringTranslationTrait;

  /**
   * D7TaxonomyTermDeriver constructor.
   *
   * @param string $basePluginId
   *   The base plugin ID for the plugin ID.
   * @param \Drupal\migrate_drupal\FieldDiscoveryInterface $fieldDiscovery
   *   The migration field discovery service.
   */
  public function __construct(
    protected $basePluginId,
    protected FieldDiscoveryInterface $fieldDiscovery,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('migrate_drupal.field_discovery')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {

    $vocabulary_source_plugin = static::getSourcePlugin('d7_taxonomy_vocabulary');
    try {
      $vocabulary_source_plugin->checkRequirements();
    }
    catch (RequirementsException) {
      // If the d7_taxonomy_vocabulary requirements failed, that means we do not
      // have a Drupal source database configured - there is nothing to
      // generate.
      return $this->derivatives;
    }

    try {
      foreach ($vocabulary_source_plugin as $row) {
        $bundle = $row->getSourceProperty('machine_name');
        $values = $base_plugin_definition;

        $values['label'] = $this->t('@label (@type)', [
          '@label' => $values['label'],
          '@type' => $row->getSourceProperty('name'),
        ]);
        $values['source']['bundle'] = $bundle;
        $values['destination']['default_bundle'] = $bundle;

        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($values);
        $this->fieldDiscovery->addBundleFieldProcesses($migration, 'taxonomy_term', $bundle);
        $this->derivatives[$bundle] = $migration->getPluginDefinition();
      }
    }
    catch (DatabaseExceptionWrapper) {
      // Once we begin iterating the source plugin it is possible that the
      // source tables will not exist. This can happen when the
      // MigrationPluginManager gathers up the migration definitions but we do
      // not actually have a Drupal 7 source database.
    }

    return $this->derivatives;
  }

}
