<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views\ViewEntityInterface;
use Drupal\views\ViewsConfigUpdater;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\views\ViewsConfigUpdater.
 */
#[CoversClass(ViewsConfigUpdater::class)]
#[Group('Views')]
#[RunTestsInSeparateProcesses]
class ViewsConfigUpdaterTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views_config_entity_test',
    'entity_test',
    'text',
    'field',
    'node',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['taxonomy']);
  }

  /**
   * Tests needsTaxonomyTermViewsFilterUpdate().
   */
  public function testNeedsTaxonomyTermViewsFilterUpdate(): void {
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    $term = Term::create(['vid' => 'tags', 'name' => 'Test term']);
    $term->save();

    $updater = $this->container->get(ViewsConfigUpdater::class);
    $updater->setDeprecationsEnabled(FALSE);

    // A view with no taxonomy filters does not need updating.
    $view = $this->buildViewWithTidFilter([]);
    $this->assertFalse($updater->needsTaxonomyTermViewsFilterUpdate($view));

    // A view whose filter values are already UUIDs does not need updating.
    $view = $this->buildViewWithTidFilter([$term->uuid()]);
    $this->assertFalse($updater->needsTaxonomyTermViewsFilterUpdate($view));

    // A view with an integer entity ID filter value needs updating.
    $view = $this->buildViewWithTidFilter([$term->id()]);
    $this->assertTrue($updater->needsTaxonomyTermViewsFilterUpdate($view));
  }

  /**
   * Tests processTaxonomyTermViewsFilterUpdate().
   */
  public function testProcessTaxonomyTermViewsFilterUpdate(): void {
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    $term = Term::create(['vid' => 'tags', 'name' => 'Test term']);
    $term->save();

    $updater = $this->container->get(ViewsConfigUpdater::class);
    $updater->setDeprecationsEnabled(FALSE);

    // A view with no taxonomy filters is not changed.
    $view = $this->buildViewWithTidFilter([]);
    $this->assertFalse($updater->processTaxonomyTermViewsFilterUpdate($view));

    // A view with UUID filter values is not changed.
    $view = $this->buildViewWithTidFilter([$term->uuid()]);
    $this->assertFalse($updater->processTaxonomyTermViewsFilterUpdate($view));

    // A view with integer entity ID values is updated.
    $view = $this->buildViewWithTidFilter([$term->id()]);
    $this->assertTrue($updater->processTaxonomyTermViewsFilterUpdate($view));

    // After processing, the filter value should be the term UUID.
    $filters = $view->get('display')['default']['display_options']['filters'];
    $this->assertContains($term->uuid(), $filters['tid']['value']);
    $this->assertNotContains($term->id(), $filters['tid']['value']);

    // Running again returns FALSE — already converted.
    $this->assertFalse($updater->processTaxonomyTermViewsFilterUpdate($view));
  }

  /**
   * Builds a test view entity with a taxonomy_index_tid filter.
   *
   * @param array $values
   *   The filter values (entity IDs or UUIDs). Pass an empty array to omit
   *   the filter entirely.
   *
   * @return \Drupal\views\ViewEntityInterface
   *   An unsaved view entity.
   */
  protected function buildViewWithTidFilter(array $values): ViewEntityInterface {
    $display_options = [];
    if (!empty($values)) {
      $display_options['filters']['tid'] = [
        'id' => 'tid',
        'table' => 'taxonomy_index',
        'field' => 'tid',
        'plugin_id' => 'taxonomy_index_tid',
        'value' => $values,
      ];
    }
    return $this->container->get('entity_type.manager')
      ->getStorage('view')
      ->create([
        'id' => 'test_taxonomy_uuid',
        'label' => 'Test',
        'base_table' => 'node_field_data',
        'display' => [
          'default' => [
            'id' => 'default',
            'display_plugin' => 'default',
            'display_title' => 'Default',
            'position' => 1,
            'display_options' => $display_options,
          ],
        ],
      ]);
  }

}
