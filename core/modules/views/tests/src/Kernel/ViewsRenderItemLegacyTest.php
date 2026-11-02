<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the legacy render_item() method for deprecation in views.
 *
 * @group views
 * @group legacy
 */
class ViewsRenderItemLegacyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views'];

  /**
   * Tests the render_item() method deprecation.
   */
  public function testRenderItemDeprecation(): void {
    $this->expectDeprecation('MultiItemsFieldHandlerInterface::render_item() is deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use renderItem() instead. See https://www.drupal.org/node/3467146');

    // Create an instance of the field plugin.
    /** @var \Drupal\views\Plugin\views\field\FieldPluginBase $fieldPlugin */
    $fieldPlugin = \Drupal::service('plugin.manager.views.field')->createInstance('field', []);

    // Use reflection to invoke the deprecated method.
    $method = new \ReflectionMethod($fieldPlugin, 'render_item');
    $method->setAccessible(TRUE);

    // Call the deprecated method.
    $method->invoke($fieldPlugin, 0, ['name' => 'Foo']);
  }

}
