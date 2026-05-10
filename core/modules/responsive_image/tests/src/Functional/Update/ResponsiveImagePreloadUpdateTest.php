<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_image\Functional\Update;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\responsive_image\ResponsiveImageConfigUpdater;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests preload upgrade path.
 */
#[CoversClass(ResponsiveImageConfigUpdater::class)]
#[Group('responsive_image')]
#[RunTestsInSeparateProcesses]
class ResponsiveImagePreloadUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/responsive_image.php',
      __DIR__ . '/../../../fixtures/update/responsive_image-loading-attribute.php',
    ];
  }

  /**
   * Test new lazy-load setting upgrade path.
   *
   * @see responsive_image_post_update_image_loading_attribute
   */
  public function testUpdate(): void {
    $data = EntityViewDisplay::load('node.article.default')->toArray();
    $this->assertArrayNotHasKey('preload', $data['content']['field_image']['settings']['image_loading'] ?? []);

    $this->runUpdates();

    $data = EntityViewDisplay::load('node.article.default')->toArray();
    $this->assertArrayHasKey('preload', $data['content']['field_image']['settings']['image_loading']);
    $this->assertEquals(FALSE, $data['content']['field_image']['settings']['image_loading']['preload']);
  }

}
