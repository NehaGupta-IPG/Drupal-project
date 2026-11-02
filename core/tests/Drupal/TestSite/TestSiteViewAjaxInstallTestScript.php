<?php

declare(strict_types=1);

namespace Drupal\TestSite;

use Drupal\Core\Extension\ModuleInstallerInterface;

/**
 * Setup file used by ajaxGetParametersTest.js.
 *
 * @see \Drupal\KernelTests\Scripts\TestSiteApplicationTest
 */
class TestSiteViewAjaxInstallTestScript implements TestSetupInterface {

  /**
   * {@inheritdoc}
   */
  public function setup(): void {
    $module_installer = \Drupal::service('module_installer');
    assert($module_installer instanceof ModuleInstallerInterface);
    $module_installer->install(['views', 'node']);

    // Enable AJAX on the /admin/content View.
    \Drupal::configFactory()->getEditable('views.view.content')
      ->set('display.default.display_options.use_ajax', TRUE)
      ->save();
  }

}
