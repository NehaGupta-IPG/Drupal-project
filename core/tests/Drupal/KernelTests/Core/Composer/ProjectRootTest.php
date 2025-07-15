<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Composer;

use Drupal\Core\Composer\ProjectRoot;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\Core\Composer\ProjectRoot
 * @group Composer
 *
 * @internal
 */
class ProjectRootTest extends KernelTestBase {

  /**
   * Exercise the code in getProjectVendorDirectory().
   *
   * We don't have a good way to unit test vendor discovery, so we must rely on
   * the behavior of a semi-installed Drupal site.
   *
   * @covers ::getProjectVendorDirectory
   */
  public function testGetProjectVendorDirectory() {
    $root = new ProjectRoot($this->container->get('app.root'));
    $vendor_root = $root->getProjectVendorDirectory();
    $this->assertFileExists($vendor_root);
    foreach (['symfony', 'composer'] as $dir) {
      $this->assertFileExists($vendor_root . '/' . $dir);
    }
  }

}
