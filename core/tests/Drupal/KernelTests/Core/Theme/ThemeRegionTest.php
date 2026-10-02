<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Theme;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests theme region listing.
 */
#[Group('Extension')]
#[RunTestsInSeparateProcesses]
class ThemeRegionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
  }

  /**
   * Tests listing a theme's regions.
   */
  public function testRegionListing(): void {
    // Install Stark.
    $this->container->get('theme_installer')->install(['stark']);
    $theme_handler = $this->container->get('theme_handler');
    $all_regions = $theme_handler->getTheme('stark')->listAllRegions();
    $visible_regions = $theme_handler->getTheme('stark')->listVisibleRegions();
    $this->assertArrayHasKey('page_top', $all_regions);
    $this->assertArrayHasKey('sidebar_first', $all_regions);
    $this->assertArrayNotHasKey('page_top', $visible_regions);
    $this->assertArrayHasKey('sidebar_first', $visible_regions);
    $this->assertEquals('sidebar_first', $theme_handler->getTheme('stark')->getDefaultRegion());
  }

}
