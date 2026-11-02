<?php

declare(strict_types=1);

namespace Drupal\Tests\taxonomy\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the legacy render_item() method for deprecation.
 *
 * @group taxonomy
 * @group legacy
 */
class TaxonomyRenderItemLegacyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['taxonomy', 'views'];

  /**
   * Tests the render_item() method deprecation.
   */
  public function testRenderItemDeprecation(): void {
    $this->expectDeprecation('MultiItemsFieldHandlerInterface::render_item() is deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use renderItem() instead. See https://www.drupal.org/node/3467146');

    /** @var \Drupal\views\Plugin\ViewsHandlerManager $viewsHandlerManager */
    $viewsHandlerManager = $this->container->get('plugin.manager.views.field');

    // Create a new instance of the TaxonomyIndexTid plugin.
    $taxonomyIndexTid = $viewsHandlerManager->createInstance('taxonomy_index_tid', []);

    // Call the deprecated render_item() method.
    $taxonomyIndexTid->render_item(0, ['name' => 'Foo']);
  }

}
