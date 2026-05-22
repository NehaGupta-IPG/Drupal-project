<?php

namespace Drupal\media\Plugin\views\wizard;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsWizard;
use Drupal\views\Plugin\views\wizard\WizardPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Views creation wizard for Media.
 */
#[ViewsWizard(
  id: 'media',
  base_table: 'media_field_data',
  title: new TranslatableMarkup('Media')
)]
class Media extends WizardPluginBase {

  /**
   * Set the created column.
   *
   * @var string
   */
  protected $createdColumn = 'media_field_data-created';

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
      $this->base_table = 'media';
      $this->createdColumn = 'media-created';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableSorts() {
    return [
      'media_field_data-name:DESC' => $this->t('Media name'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultDisplayOptions() {
    $display_options = parent::defaultDisplayOptions();

    // Add permission-based access control.
    $display_options['access']['type'] = 'perm';
    $display_options['access']['options']['perm'] = 'view media';

    // Remove the default fields, since we are customizing them here.
    unset($display_options['fields']);

    // Add the name field, so that the display has content if the user switches
    // to a row style that uses fields.
    $display_options['fields']['name']['id'] = 'name';
    if ($this->connection->driver() == 'mongodb') {
      $display_options['fields']['name']['table'] = 'media';
    }
    else {
      $display_options['fields']['name']['table'] = 'media_field_data';
    }
    $display_options['fields']['name']['field'] = 'name';
    $display_options['fields']['name']['entity_type'] = 'media';
    $display_options['fields']['name']['entity_field'] = 'media';
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
    $display_options['fields']['name']['settings']['link_to_entity'] = 1;
    $display_options['fields']['name']['plugin_id'] = 'field';

    return $display_options;
  }

}
