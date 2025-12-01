<?php

namespace Drupal\Tests\image\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests lazy-load upgrade path.
 *
 * @group image
 */
class ImagePreloadUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Test new lazy-load setting upgrade path.
   *
   * @see image_post_update_image_loading_attribute
   */
  public function testUpdate() {
    $storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display */
    $view_display = $storage->load('node.article.default');
    $component = $view_display->getComponent('field_image');
    $this->assertArrayNotHasKey('preload', $component['settings']['image_loading']);
    $this->runUpdates();
    $view_display = $storage->load('node.article.default');
    $component = $view_display->getComponent('field_image');
    $this->assertArrayHasKey('preload', $component['settings']['image_loading']);
    $this->assertEquals(FALSE, $component['settings']['image_loading']['preload']);
  }

}
