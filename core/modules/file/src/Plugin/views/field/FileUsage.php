<?php

namespace Drupal\file\Plugin\views\field;

use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to provide file usage count.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("file_usage")]
class FileUsage extends FieldPluginBase {

  /**
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected FileUsageInterface $fileUsage;

  /**
   * Stores pre-loaded usage data keyed by file ID.
   *
   * @var array
   */
  protected array $usageData = [];

  /**
   * Constructs a new FileUsage instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   The file usage service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FileUsageInterface $file_usage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileUsage = $file_usage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file.usage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // This field doesn't add anything to the query.
    // We fetch the data in preRender() using a single batch query.
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    // File usage count cannot be sorted in the query.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values): void {
    // Collect all file IDs from the result set.
    $file_ids = [];
    foreach ($values as $row) {
      /** @var \Drupal\file\FileInterface|null $entity */
      $entity = $this->getEntity($row);
      if ($entity) {
        $file_ids[] = $entity->id();
      }
    }

    // Batch load all usage data in a single query.
    if (!empty($file_ids)) {
      $this->usageData = $this->fileUsage->listUsageMultiple($file_ids);
    }
  }

  /**
   * Sums up the usages of a deep usages array.
   *
   * @param array $usages
   *   The usages array structured as [module][type][id] => count.
   *
   * @return int
   *   The total usage count.
   */
  protected function sumUsages(array $usages): int {
    $sum = 0;
    array_walk_recursive($usages, function ($item) use (&$sum) {
      $sum += $item;
    });
    return $sum;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\file\FileInterface|null $entity */
    $entity = $this->getEntity($values);
    if (!$entity) {
      return '0';
    }

    // Use pre-loaded data from preRender().
    $usage = $this->usageData[$entity->id()] ?? [];
    $total = $this->sumUsages($usage);

    return $this->formatPlural($total, '@count place', '@count places');
  }

}
