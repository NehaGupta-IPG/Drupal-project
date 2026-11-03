<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests installing the Testing profile with update notifications on.
 */
#[Group('Installer')]
#[RunTestsInSeparateProcesses]
class TestingProfileInstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Ensure the Update Status module is installed.
   */
  public function testUpdateModuleInstall(): void {
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('update'));
    $this->assertFalse($this->config('update.settings')->get('data_sharing.domain'));
    $this->assertNull($this->config('update.settings')->get('data_sharing.site_mail'));
  }

  /**
   * {@inheritdoc}
   */
  protected function installParameters(): array {
    $params = parent::installParameters();
    $params['forms']['install_configure_form']['enable_update_status_module'] = TRUE;
    // NULL unchecks a checkbox in a programmatic form submission.
    $params['forms']['install_configure_form']['share_domain'] = NULL;
    return $params;
  }

}
