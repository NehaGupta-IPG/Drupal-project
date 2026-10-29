<?php

declare(strict_types=1);

namespace Drupal\locale\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by version.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsField("locale_version")]
class Version extends InOperator {

  /**
   * Database Service Object.
   */
  protected Connection $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions(): void {
    if (!isset($this->value_options)) {
      // Enable filtering by the current installed Drupal version.
      $versions = ['***CURRENT_VERSION***' => $this->t('Current installed version')];
      // Uses db_query() rather than db_select() because the query is static and
      // does not include any variables.
      $result = $this->database->query('SELECT DISTINCT(version) FROM {locales_source} ORDER BY version');
      foreach ($result as $row) {
        if (!empty($row->version)) {
          $versions[$row->version] = $row->version;
        }
      }
      $this->valueOptions = $versions;
    }
  }

}
