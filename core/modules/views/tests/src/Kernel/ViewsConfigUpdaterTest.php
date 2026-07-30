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
   * Tests the `needsDisableAjaxBookmarkingUpdate` method.
   */
  public function testNeedsDisableAjaxBookmarkingUpdate(): void {
    /** @var \Drupal\views\ViewsConfigUpdater $view_config_updater */
    $view_config_updater = \Drupal::classResolver(ViewsConfigUpdater::class);
    $view_config_updater->setDeprecationsEnabled(FALSE);

    $test_view = $this->loadTestView('views.view.test_disable_ajax_bookmarking');
    $this->assertTrue($view_config_updater->needsDisableAjaxBookmarkingUpdate($test_view));

    // Displays containing the use_ajax setting get the new setting.
    $display = $test_view->getDisplay('default');
    $this->assertFalse($display['display_options']['disable_ajax_bookmarking']);
    $display = $test_view->getDisplay('block_1');
    $this->assertFalse($display['display_options']['disable_ajax_bookmarking']);

    // Displays without the use_ajax setting are left alone.
    $display = $test_view->getDisplay('page_1');
    $this->assertArrayNotHasKey('disable_ajax_bookmarking', $display['display_options']);

    // The update is idempotent.
    $this->assertFalse($view_config_updater->needsDisableAjaxBookmarkingUpdate($test_view));
  }

  /**
   * Tests that the disable_ajax_bookmarking setting is added on save.
   */
  #[IgnoreDeprecations]
  public function testDisableAjaxBookmarkingUpdateOnSaveHandler(): void {
    $this->installEntitySchema('user');

    $test_view = $this->loadTestView('views.view.test_disable_ajax_bookmarking');

    $this->expectUserDeprecationMessage('The update to add the disable_ajax_bookmarking setting for view "test_disable_ajax_bookmarking" is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Profile, module and theme provided configuration should be updated. See https://www.drupal.org/node/3604351');
    $test_view->save();

    $display = $test_view->getDisplay('default');
    $this->assertFalse($display['display_options']['disable_ajax_bookmarking']);
    $display = $test_view->getDisplay('block_1');
    $this->assertFalse($display['display_options']['disable_ajax_bookmarking']);
    $display = $test_view->getDisplay('page_1');
    $this->assertArrayNotHasKey('disable_ajax_bookmarking', $display['display_options']);
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
