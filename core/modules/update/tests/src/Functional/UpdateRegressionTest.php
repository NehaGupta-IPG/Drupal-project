<?php

declare(strict_types=1);

namespace Drupal\Tests\update\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Update module regression tests.
 *
 * @group update
 *
 * @internal
 */
class UpdateRegressionTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['update'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an admin user.
    $admin_user = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

  }

  /**
   * Ensure `notification.emails` config set as a string doesn't throw an error.
   *
   * @see https://www.drupal.org/project/drupal/issues/3353778
   */
  public function testNotificationEmailsAsStringDoesNotThrowAnErrorFromIssue3353778(): void {

    // We cannot use the config factory to update the config because
    // the config schema checker will throw an exception. Instead, we
    // update the config directly through the config storage.
    /** @var \Drupal\Core\Config\CachedStorage $configStorage */
    $configStorage = \Drupal::service('config.storage');

    // 'notification.emails' being a string.
    $updateSettings = $configStorage->read('update.settings');
    $updateSettings['notification']['emails'] = 'llama@drupal.org';
    $configStorage->write('update.settings', $updateSettings);
    $this->drupalGet(Url::fromRoute('update.settings'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('update_notify_emails', 'llama@drupal.org');

    // 'notification.emails' being NULL.
    $updateSettings['notification']['emails'] = NULL;
    $configStorage->write('update.settings', $updateSettings);
    $this->drupalGet(Url::fromRoute('update.settings'));
    $this->assertSession()->fieldValueEquals('update_notify_emails', '');

  }

}
