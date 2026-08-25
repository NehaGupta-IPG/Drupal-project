<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\views\ViewsConfigUpdater;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
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
  ];

  /**
   * Tests the removal of the custom lifespan options of time-based caching.
   */
  #[IgnoreDeprecations]
  public function testNeedsTimeCacheUpdate(): void {
    $config_updater = $this->container
      ->get('class_resolver')
      ->getInstanceFromDefinition(ViewsConfigUpdater::class);
    assert($config_updater instanceof ViewsConfigUpdater);

    $test_view = $this->loadTestView('views.view.test_time_cache_update');
    $this->assertTrue($config_updater->needsTimeCacheUpdate($test_view));
    $test_view->save();

    // A custom lifespan was stored as 0 in the main option with the actual
    // value in the "_custom" option, but unused.
    $default_options = $test_view->getDisplay('default')['display_options']['cache']['options'];
    $this->assertSame(0, $default_options['results_lifespan']);
    $this->assertArrayNotHasKey('results_lifespan_custom', $default_options);
    $this->assertSame(3600, $default_options['output_lifespan']);
    $this->assertArrayNotHasKey('output_lifespan_custom', $default_options);

    // Configuration that was never cast by the config schema stores the
    // string "custom" in the main option, which cannot be kept: the
    // configured custom lifespan is applied.
    $page_options = $test_view->getDisplay('page_1')['display_options']['cache']['options'];
    $this->assertSame(100, $page_options['results_lifespan']);
    $this->assertArrayNotHasKey('results_lifespan_custom', $page_options);
    // "Never cache" with no custom value must be kept.
    $this->assertSame(0, $page_options['output_lifespan']);
    $this->assertArrayNotHasKey('output_lifespan_custom', $page_options);

    // The updated view does not need the update anymore.
    $this->assertFalse($config_updater->needsTimeCacheUpdate($test_view));
  }

  /**
   * Loads a test view.
   *
   * @param string $view_id
   *   The view config ID.
   *
   * @return \Drupal\views\ViewEntityInterface
   *   A view entity object.
   */
  protected function loadTestView($view_id) {
    // We just instantiate the test view from the raw configuration, as it may
    // not be possible to save it, due to its faulty schema.
    $config_dir = $this->getModulePath('views') . '/tests/fixtures/update';
    $file_storage = new FileStorage($config_dir);
    $values = $file_storage->read($view_id);
    /** @var \Drupal\views\ViewEntityInterface $test_view */
    $test_view = $this->container
      ->get('entity_type.manager')
      ->getStorage('view')
      ->create($values);
    return $test_view;
  }

}
