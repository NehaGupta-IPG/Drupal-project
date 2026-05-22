<?php

namespace Drupal\comment\Plugin\views\wizard;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsWizard;
use Drupal\views\Plugin\views\wizard\WizardPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @todo replace numbers with constants.
 */

/**
 * Tests creating comment views with the wizard.
 */
#[ViewsWizard(
  id: 'comment',
  base_table: 'comment_field_data',
  title: new TranslatableMarkup('Comments')
)]
class Comment extends WizardPluginBase {

  /**
   * Set the created column.
   *
   * @var string
   */
  protected $createdColumn = 'created';

  /**
   * Set default values for the filters.
   */
  protected $filters = [
    'status_node' => [
      'value' => TRUE,
      'table' => 'node_field_data',
      'field' => 'status',
      'plugin_id' => 'boolean',
      'relationship' => 'node',
      'entity_type' => 'node',
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
      $this->base_table = 'comment';
      $this->filters['status_node']['table'] = 'node';
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function rowStyleOptions() {
    $options = [];
    $options['entity:comment'] = $this->t('comments');
    $options['fields'] = $this->t('fields');
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultDisplayOptions() {
    $display_options = parent::defaultDisplayOptions();

    // Add permission-based access control.
    $display_options['access']['type'] = 'perm';
    $display_options['access']['options']['perm'] = 'access comments';

    // Add a relationship to nodes.
    $display_options['relationships']['node']['id'] = 'node';
    if ($this->connection->driver() == 'mongodb') {
      $display_options['relationships']['node']['table'] = 'comment';
    }
    else {
      $display_options['relationships']['node']['table'] = 'comment_field_data';
    }
    $display_options['relationships']['node']['field'] = 'node';
    if ($this->connection->driver() == 'mongodb') {
      $display_options['relationships']['node']['entity_type'] = 'comment';
    }
    else {
      $display_options['relationships']['node']['entity_type'] = 'comment_field_data';
    }
    $display_options['relationships']['node']['required'] = 1;
    $display_options['relationships']['node']['plugin_id'] = 'standard';

    // Remove the default fields, since we are customizing them here.
    unset($display_options['fields']);

    /* Field: Comment: Title */
    $display_options['fields']['subject']['id'] = 'subject';
    if ($this->connection->driver() == 'mongodb') {
      $display_options['fields']['subject']['table'] = 'comment';
    }
    else {
      $display_options['fields']['subject']['table'] = 'comment_field_data';
    }
    $display_options['fields']['subject']['field'] = 'subject';
    $display_options['fields']['subject']['entity_type'] = 'comment';
    $display_options['fields']['subject']['entity_field'] = 'subject';
    $display_options['fields']['subject']['label'] = '';
    $display_options['fields']['subject']['alter']['alter_text'] = 0;
    $display_options['fields']['subject']['alter']['make_link'] = 0;
    $display_options['fields']['subject']['alter']['absolute'] = 0;
    $display_options['fields']['subject']['alter']['trim'] = 0;
    $display_options['fields']['subject']['alter']['word_boundary'] = 0;
    $display_options['fields']['subject']['alter']['ellipsis'] = 0;
    $display_options['fields']['subject']['alter']['strip_tags'] = 0;
    $display_options['fields']['subject']['alter']['html'] = 0;
    $display_options['fields']['subject']['hide_empty'] = 0;
    $display_options['fields']['subject']['empty_zero'] = 0;
    $display_options['fields']['subject']['plugin_id'] = 'field';
    $display_options['fields']['subject']['type'] = 'string';
    $display_options['fields']['subject']['settings'] = ['link_to_entity' => TRUE];

    return $display_options;
  }

}
