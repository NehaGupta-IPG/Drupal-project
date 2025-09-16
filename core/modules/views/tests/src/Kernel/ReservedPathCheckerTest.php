<?php

namespace Drupal\Tests\views\Kernel;

/**
 * @coversDefaultClass \Drupal\views\ReservedPathChecker
 *
 * @group views
 */
class ReservedPathCheckerTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'views',
  ];

  /**
   * Tests listReservedFirstSegments
   */
  public function testListReservedFirstSegments(): void {
    /** @var \Drupal\views\ReservedPathChecker $service */
    $service = \Drupal::service('views.reserved_path_checker');

    $list = $service->listReservedFirstSegments();
    $this->assertIsArray($list);
    $this->assertNotEmpty($list, 'The reserved list should not be empty.');

    // Common docroot directories in a standard Drupal installation.
    $this->assertContains('core', $list);
    $this->assertContains('sites', $list);
    $this->assertContains('themes', $list);
    $this->assertContains('modules', $list);

    // Ensure files are not included.
    $this->assertNotContains('index.php', $list);
    $this->assertNotContains('update.php', $list);
  }

  /**
   * Tests firstSegmentFromPath
   */
  public function testFirstSegmentFromPath(): void {
    /** @var \Drupal\views\ReservedPathChecker $service */
    $service = \Drupal::service('views.reserved_path_checker');

    $this->assertSame('', $service->firstSegmentFromPath(''));
    $this->assertSame('', $service->firstSegmentFromPath('/'));
    $this->assertSame('core', $service->firstSegmentFromPath('core'));
    $this->assertSame('core', $service->firstSegmentFromPath('/core'));
    $this->assertSame('core', $service->firstSegmentFromPath('/core/example'));
    $this->assertSame('CORE', $service->firstSegmentFromPath('CORE//x'));
    $this->assertSame('some', $service->firstSegmentFromPath('some/path'));
    $this->assertSame('some', $service->firstSegmentFromPath(' some '));
    $this->assertSame('spaced', $service->firstSegmentFromPath('/spaced/%20path'));
  }

  /**
   * Tests isReservedFirstSegment
   */
  public function testIsReservedFirstSegment(): void {
    /** @var \Drupal\views\ReservedPathChecker $service */
    $service = \Drupal::service('views.reserved_path_checker');

    // Positive cases (should be TRUE).
    $this->assertTrue($service->isReservedFirstSegment('core'));
    $this->assertTrue($service->isReservedFirstSegment('/core/example'));
    $this->assertTrue($service->isReservedFirstSegment('CORE'));

    // Negative cases (should be FALSE).
    $this->assertFalse($service->isReservedFirstSegment('library'));
    $this->assertFalse($service->isReservedFirstSegment('not-existing-first-segment/example'));
    $this->assertFalse($service->isReservedFirstSegment(''));
    $this->assertFalse($service->isReservedFirstSegment('/'));
  }

}
