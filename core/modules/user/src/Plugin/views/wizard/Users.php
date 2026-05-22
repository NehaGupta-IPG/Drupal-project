<?php

namespace Drupal\user\Plugin\views\wizard;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsWizard;
use Drupal\views\Plugin\views\wizard\WizardPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @todo Replace numbers with constants.
 */

/**
 * Tests creating user views with the wizard.
 */
#[ViewsWizard(
  id: 'users',
  title: new TranslatableMarkup('Users'),
  base_table: 'users_field_data'
)]
class Users extends WizardPluginBase {

  /**
   * Set the created column.
   *
   * @var string
   */
  protected $createdColumn = 'created';

  /**
   * Set default values for the filters.
   *
   * @var string[]
   */
  protected $filters = [
    'status' => [
      'value' => TRUE,
      'table' => 'users_field_data',
      'field' => 'status',
      'plugin_id' => 'boolean',
      'entity_type' => 'user',
      'entity_field' => 'status',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.bundle.info'),
      $container->get('menu.parent_form_selector'),
      $container->get('database')
    );
  }

  /**
   * Constructs a WizardPluginBase object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeBundleInfoInterface $bundle_info_service, MenuParentFormSelectorInterface $parent_form_selector, Connection $connection) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $bundle_info_service, $parent_form_selector, $connection);

    if ($connection->driver() == 'mongodb') {
      $this->base_table = 'users';
      $this->filters['status']['table'] = 'users';
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultDisplayOptions() {
    $display_options = parent::defaultDisplayOptions();

    // Add permission-based access control.
    $display_options['access']['type'] = 'perm';
    $display_options['access']['options']['perm'] = 'access user profiles';

    // Remove the default fields, since we are customizing them here.
    unset($display_options['fields']);

    /* Field: User: Name */
    $display_options['fields']['name']['id'] = 'name';
    if ($this->connection->driver() == 'mongodb') {
      $display_options['fields']['name']['table'] = 'users';
    }
    else {
      $display_options['fields']['name']['table'] = 'users_field_data';
    }
    $display_options['fields']['name']['field'] = 'name';
    $display_options['fields']['name']['entity_type'] = 'user';
    $display_options['fields']['name']['entity_field'] = 'name';
    $display_options['fields']['name']['label'] = '';
    $display_options['fields']['name']['alter']['alter_text'] = 0;
    $display_options['fields']['name']['alter']['make_link'] = 0;
    $display_options['fields']['name']['alter']['absolute'] = 0;
    $display_options['fields']['name']['alter']['trim'] = 0;
    $display_options['fields']['name']['alter']['word_boundary'] = 0;
    $display_options['fields']['name']['alter']['ellipsis'] = 0;
    $display_options['fields']['name']['alter']['strip_tags'] = 0;
    $display_options['fields']['name']['alter']['html'] = 0;
    $display_options['fields']['name']['hide_empty'] = 0;
    $display_options['fields']['name']['empty_zero'] = 0;
    $display_options['fields']['name']['plugin_id'] = 'field';

    return $display_options;
  }

}
