<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid_reorder\Nightwatch;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\TestSite\TestSetupInterface;

/**
 * Setup file for the treegrid_reorder Nightwatch tests.
 *
 * Installs the treegrid_reorder_test consumer module, whose page attaches the
 * reorder libraries the tests exercise.
 */
class TestSiteTreegridReorderInstallTestScript implements TestSetupInterface {

  /**
   * {@inheritdoc}
   */
  public function setup(): void {
    $module_installer = \Drupal::service('module_installer');
    assert($module_installer instanceof ModuleInstallerInterface);
    $module_installer->install(['treegrid_reorder_test']);
  }

}
