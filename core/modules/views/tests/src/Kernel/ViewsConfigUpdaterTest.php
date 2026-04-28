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
    'breakpoint',
    'text',
    'field',
    'node',
    'file',
    'image',
    'responsive_image',
    'responsive_image_test_module',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('file');
  }

  /**
   * Tests ViewsConfigUpdater.
   */
  public function testViewsConfigUpdater(): void {
    // ViewsConfigUpdater currently contains no actual configuration update
    // logic. Replace this method with a real test when it does.
    $this->markTestSkipped();
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
      'field_name' => 'image_test',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'image_test',
    ])->save();

    $test_view = $this->loadTestView('views.view.test_responsive_images');
    $needs_update = $config_updater->needsImagePreloadFieldUpdate($test_view);
    $test_view->save();
    $this->assertTrue($needs_update);

    $default_display = $test_view->getDisplay('default');
    self::assertEquals('eager', $default_display['display_options']['fields']['image_test']['settings']['image_loading']['attribute']);
    self::assertEquals(FALSE, $default_display['display_options']['fields']['image_test']['settings']['image_loading']['preload']);
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
      'field_name' => 'image_test',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'image_test',
    ])->save();

    $test_view = $this->loadTestView('views.view.test_images');
    $needs_update = $config_updater->needsImagePreloadFieldUpdate($test_view);
    $test_view->save();
    $this->assertTrue($needs_update);

    $default_display = $test_view->getDisplay('default');
    self::assertEquals(FALSE, $default_display['display_options']['fields']['image_test']['settings']['image_loading']['preload']);
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
