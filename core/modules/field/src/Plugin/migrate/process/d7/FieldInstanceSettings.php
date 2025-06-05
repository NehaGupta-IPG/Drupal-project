<?php

namespace Drupal\field\Plugin\migrate\process\d7;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

// cspell:ignore entityreference

/**
 * Determines the field instance settings.
 */
#[MigrateProcess(
 id: "d7_field_instance_settings"
)]
class FieldInstanceSettings extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The migrate lookup service.
   *
   * @var \Drupal\migrate\MigrateLookupInterface
   */
  protected $migrateLookup;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a FieldInstanceSettings object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\MigrateLookupInterface $migrate_lookup
   *   The migrate lookup service.
   */
  // @codingStandardsIgnoreLine
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrateLookupInterface $migrate_lookup, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migrateLookup = $migrate_lookup;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('migrate.lookup'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    [$instance_settings, $widget_settings, $field_definition] = $value;
    $widget_type = $widget_settings['type'];

    $field_data = unserialize($field_definition['data']);

    // Get taxonomy term reference handler settings from allowed values.
    if ($row->getSourceProperty('type') == 'taxonomy_term_reference') {
      $instance_settings['handler_settings']['sort'] = [
        'field' => '_none',
      ];
      $allowed_values = $row->get('@allowed_values');
      foreach ($allowed_values as $allowed_value) {
        foreach ($allowed_value as $vocabulary) {
          $instance_settings['handler_settings']['target_bundles'][$vocabulary] = $vocabulary;
        }
      }
    }

    // Get entityreference handler settings from source field configuration.
    if ($row->getSourceProperty('type') == "entityreference") {
      $field_settings = $field_data['settings'];
      $instance_settings['handler'] = 'default:' . $field_settings['target_type'];
      // Transform the sort settings to D8 structure.
      $sort = [
        'field' => '_none',
        'direction' => 'ASC',
      ];
      if (!empty(array_filter($field_settings['handler_settings']['sort']))) {
        if ($field_settings['handler_settings']['sort']['type'] == "property") {
          $sort = [
            'field' => $field_settings['handler_settings']['sort']['property'],
            'direction' => $field_settings['handler_settings']['sort']['direction'],
          ];
        }
        elseif ($field_settings['handler_settings']['sort']['type'] == "field") {
          $sort = [
            'field' => $field_settings['handler_settings']['sort']['field'],
            'direction' => $field_settings['handler_settings']['sort']['direction'],
          ];
        }
      }
      if (empty($field_settings['handler_settings']['target_bundles'])) {
        $field_settings['handler_settings']['target_bundles'] = NULL;
      }
      $field_settings['handler_settings']['sort'] = $sort;
      $instance_settings['handler_settings'] = $field_settings['handler_settings'];
    }

    if ($row->getSourceProperty('type') == 'node_reference') {
      $instance_settings['handler'] = 'default:node';

      $target_bundles = array_filter($field_data['settings']['referenceable_types']);
      if (!empty($target_bundles)) {
        $dest_bundles = [];
        foreach ($target_bundles as $bundle) {
          $lookup_result = $this->migrateLookup->lookup('d7_node_type', [$bundle]);
          if ($lookup_result) {
            $dest_bundles[$lookup_result[0]['type']] = $lookup_result[0]['type'];
          }
        }
        $target_bundles = $dest_bundles;
      }
      else {
        // @see field_field_config_presave()
        $target_bundles = array_keys($this->entityTypeManager->getStorage('node_type')->loadMultiple());
      }

      $instance_settings['handler_settings'] = [
        'sort' => [
          'field' => '_none',
          'direction' => 'ASC',
        ],
        'target_bundles' => $target_bundles,
      ];
    }

    if ($row->getSourceProperty('type') == 'user_reference') {
      $instance_settings['handler'] = 'default:user';

      $instance_settings['handler_settings'] = [
        'include_anonymous' => TRUE,
        'filter' => [
          'type' => '_none',
        ],
        'sort' => [
          'field' => '_none',
          'direction' => 'ASC',
        ],
        'auto_create' => FALSE,
      ];

      if ($row->hasSourceProperty('roles')) {
        foreach ($row->get('roles') as $role) {
          $lookup_result = $this->migrateLookup->lookup('d7_user_role', [$role['rid']]);
          if (!$lookup_result) {
            continue;
          }
          $dest_role_id = $lookup_result[0]['id'];
          // @see \Drupal\user\Plugin\EntityReferenceSelection\UserSelection::buildConfigurationForm()
          if ($dest_role_id === RoleInterface::AUTHENTICATED_ID) {
            $instance_settings['handler_settings']['include_anonymous'] = FALSE;
            continue;
          }
          $instance_settings['handler_settings']['filter']['type'] = 'role';
          $instance_settings['handler_settings']['filter']['role'] = [
            $dest_role_id => $dest_role_id,
          ];
        }
      }
    }

    // Get the labels for the list_boolean type.
    if ($row->getSourceProperty('type') === 'list_boolean') {
      if (isset($field_data['settings']['allowed_values'][1])) {
        $instance_settings['on_label'] = $field_data['settings']['allowed_values'][1];
      }
      if (isset($field_data['settings']['allowed_values'][0])) {
        $instance_settings['off_label'] = $field_data['settings']['allowed_values'][0];
      }
    }

    switch ($widget_type) {
      case 'image_image':
      case 'image_miw':
        $settings = $instance_settings;
        $settings['default_image'] = [
          'alt' => '',
          'title' => '',
          'width' => NULL,
          'height' => NULL,
          'uuid' => '',
        ];
        break;

      default:
        $settings = $instance_settings;
    }

    return $settings;
  }

}
