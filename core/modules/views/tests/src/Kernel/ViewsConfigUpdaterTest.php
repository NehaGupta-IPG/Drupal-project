<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\Tests\responsive_image\Functional\ViewsIntegrationTest;
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
#[IgnoreDeprecations]
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
   * Tests ViewsConfigUpdater.
   */
  public function testViewsConfigUpdater(): void {
    // ViewsConfigUpdater currently contains no actual configuration update
    // logic. Replace this method with a real test when it does.
    $this->markTestSkipped();
  }

  /**
   * Tests the `needsRssViewModeUpdate` method.
   */
  public function testUpdateRssViewMode(): void {
    $this->strictConfigSchema = FALSE;

    /** @var \Drupal\views\ViewsConfigUpdater $view_config_updater */
    $view_config_updater = \Drupal::classResolver(ViewsConfigUpdater::class);

    // Load the test view.
    $view_id = 'views.view.test_display_feed';
    $test_view = $this->loadTestView($view_id);
    $display = $test_view->getDisplay('feed_1');

    // Check the initial view mode.
    $rowViewMode = $display['display_options']['row']['options']['view_mode'] ?? FALSE;
    $this->assertEquals('default', $rowViewMode);

    // Update the view mode using the method under test.
    $view_config_updater->needsRssViewModeUpdate($test_view, 'old_view_mode');

    // Assert if the view mode was updated correctly.
    $display = $test_view->getDisplay('feed_1');
    $updatedRowViewMode = $display['display_options']['row']['options']['view_mode'] ?? FALSE;
    $this->assertEquals('old_view_mode', $updatedRowViewMode);
  }

  /**
   * Tests the `needsRssViewModeUpdate` method.
   */
  public function testUpdateRssViewModeWithoutKnownPreviousMode(): void {
    $this->installEntitySchema('node');
    $this->installConfig(['text', 'field', 'node']);

    $this->strictConfigSchema = FALSE;

    /** @var \Drupal\views\ViewsConfigUpdater $view_config_updater */
    $view_config_updater = \Drupal::classResolver(ViewsConfigUpdater::class);

    // Load the test view.
    $view_id = 'views.view.test_display_feed';
    $test_view = $this->loadTestView($view_id);
    $display = $test_view->getDisplay('feed_1');

    // Check the initial view mode.
    $rowViewMode = $display['display_options']['row']['options']['view_mode'] ?? FALSE;
    $this->assertEquals('default', $rowViewMode);

    // Update the view mode using the method under test.
    $view_config_updater->needsRssViewModeUpdate($test_view);

    // Assert if the view mode was updated correctly.
    $display = $test_view->getDisplay('feed_1');
    $updatedRowViewMode = $display['display_options']['row']['options']['view_mode'] ?? FALSE;

    // This should be the first node view mode since we have nothing else
    $this->assertEquals('rss', $updatedRowViewMode);
  }

  /**
   * Tests if onSave also updates the view.
   */
  public function testUpdateRssViewModeWithoutKnownPreviousModeOnSaveHandler(): void {
    $this->installEntitySchema('node');
    $this->installConfig(['text', 'field', 'node']);

    $this->strictConfigSchema = FALSE;

    // Load the test view.
    $view_id = 'views.view.test_display_feed';
    $test_view = $this->loadTestView($view_id);
    $display = $test_view->getDisplay('feed_1');

    // Check the initial view mode.
    $rowViewMode = $display['display_options']['row']['options']['view_mode'] ?? FALSE;
    $this->assertEquals('default', $rowViewMode);

    $test_view->save();

    // Assert if the view mode was updated correctly onSave.
    $display = $test_view->getDisplay('feed_1');
    $updatedRowViewMode = $display['display_options']['row']['options']['view_mode'] ?? FALSE;

    // This should be the first node view mode since we have nothing else
    $this->assertEquals('rss', $updatedRowViewMode);
  }

  /**
   * Tests the `needsRssViewModeUpdate` method.
   */
  public function testUpdateRssViewModeSkipsOtherType(): void {
    $this->strictConfigSchema = FALSE;

    /** @var \Drupal\views\ViewsConfigUpdater $view_config_updater */
    $view_config_updater = \Drupal::classResolver(ViewsConfigUpdater::class);

    // Load the test view.
    $view_id = 'views.view.test_display_feed_no_update';
    $test_view = $this->loadTestView($view_id);
    $display = $test_view->getDisplay('feed_1');

    // Check the initial view mode.
    $rowViewMode = $display['display_options']['row']['options']['view_mode'] ?? FALSE;
    $this->assertEquals('default', $rowViewMode);

    // Update the view mode using the method under test.
    $view_config_updater->needsRssViewModeUpdate($test_view, 'old_view_mode');

    // Assert if the view mode was updated correctly.
    $display = $test_view->getDisplay('feed_1');
    $updatedRowViewMode = $display['display_options']['row']['options']['view_mode'] ?? FALSE;
    $this->assertEquals('default', $updatedRowViewMode);
  }

  /**
   * Test the `needsImagePreloadFieldUpdate` method.
   */
  public function testNeedsResponsiveImagePreloadFieldUpdate(): void {
    $config_updater = $this->container
      ->get('class_resolver')
      ->getInstanceFromDefinition(ViewsConfigUpdater::class);
    assert($config_updater instanceof ViewsConfigUpdater);

    // Create a responsive image style.
    ResponsiveImageStyle::create([
      'id' => ViewsIntegrationTest::RESPONSIVE_IMAGE_STYLE_ID,
      'label' => 'Foo',
      'breakpoint_group' => 'responsive_image_test_module',
    ]);
    // Create an image field to be used with a responsive image formatter.
    FieldStorageConfig::create([
      'type' => 'image',
      'entity_type' => 'entity_test',
      'field_name' => 'bar',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'bar',
    ])->save();

    $test_view = $this->loadTestView('views.view.test_responsive_images');
    $needs_update = $config_updater->needsResponsiveImageLazyLoadFieldUpdate($test_view);
    $needs_update = $config_updater->needsImagePreloadFieldUpdate($test_view);
    $test_view->save();
    $this->assertTrue($needs_update);

    $default_display = $test_view->getDisplay('default');
    self::assertEquals('eager', $default_display['display_options']['fields']['bar']['settings']['image_loading']['attribute']);
    self::assertEquals(FALSE, $default_display['display_options']['fields']['bar']['settings']['image_loading']['preload']);
  }

  /**
   * Test the `needsImagePreloadFieldUpdate` method.
   */
  public function testNeedsImagePreloadFieldUpdate(): void {
    $config_updater = $this->container
      ->get('class_resolver')
      ->getInstanceFromDefinition(ViewsConfigUpdater::class);
    assert($config_updater instanceof ViewsConfigUpdater);

    // Create an image field to be used with a responsive image formatter.
    FieldStorageConfig::create([
      'type' => 'image',
      'entity_type' => 'entity_test',
      'field_name' => 'bar',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'bar',
    ])->save();

    $test_view = $this->loadTestView('views.view.test_images');
    $needs_update = $config_updater->needsImagePreloadFieldUpdate($test_view);
    $test_view->save();
    $this->assertTrue($needs_update);

    $default_display = $test_view->getDisplay('default');
    self::assertEquals(FALSE, $default_display['display_options']['fields']['bar']['settings']['image_loading']['preload']);
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
