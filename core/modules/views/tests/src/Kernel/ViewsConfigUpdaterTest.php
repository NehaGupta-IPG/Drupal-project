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
   * Tests updateTableSortableCounter.
   */
  #[IgnoreDeprecations]
  public function testUpdateTableSortableCounter(): void {
    $this->expectUserDeprecationMessage('The update to make the result counter field non-sortable in table displays for view "test_table_sortable_counter" is deprecated in drupal:12.0.0 and is removed from drupal:13.0.0. Profile, module and theme provided configuration should be updated. See https://www.drupal.org/node/3588188');

    $view = $this->loadTestView('views.view.test_table_sortable_counter');
    $display = $view->get('display');
    $this->assertTrue($display['default']['display_options']['style']['options']['info']['counter']['sortable']);
    $this->assertTrue($display['default']['display_options']['style']['options']['info']['counter_alias']['sortable']);
    $this->assertTrue($display['default']['display_options']['style']['options']['info']['name']['sortable']);
    $non_table_display = $display['page_1'];

    /** @var \Drupal\views\ViewsConfigUpdater $views_config_updater */
    $views_config_updater = $this->container->get(ViewsConfigUpdater::class);
    $this->assertTrue($views_config_updater->updateAll($view));

    $display = $view->get('display');
    $this->assertFalse($display['default']['display_options']['style']['options']['info']['counter']['sortable']);
    $this->assertFalse($display['default']['display_options']['style']['options']['info']['counter_alias']['sortable']);
    $this->assertTrue($display['default']['display_options']['style']['options']['info']['name']['sortable']);
    $this->assertSame($non_table_display, $display['page_1']);
    $this->assertFalse($views_config_updater->updateAll($view));

    // Loading the same view again and updating it should not trigger the
    // deprecation a second time.
    $view2 = $this->loadTestView('views.view.test_table_sortable_counter');
    $this->assertTrue($views_config_updater->updateAll($view2));
  }

  /**
   * Tests updateTableSortableCounter() with deprecations disabled.
   */
  public function testUpdateTableSortableCounterDeprecationsDisabled(): void {
    $view = $this->loadTestView('views.view.test_table_sortable_counter');

    /** @var \Drupal\views\ViewsConfigUpdater $views_config_updater */
    $views_config_updater = $this->container->get(ViewsConfigUpdater::class);
    $views_config_updater->setDeprecationsEnabled(FALSE);
    $this->assertTrue($views_config_updater->updateAll($view));

    $display = $view->get('display');
    $this->assertFalse($display['default']['display_options']['style']['options']['info']['counter']['sortable']);
    $this->assertFalse($display['default']['display_options']['style']['options']['info']['counter_alias']['sortable']);
    $this->assertTrue($display['default']['display_options']['style']['options']['info']['name']['sortable']);
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
