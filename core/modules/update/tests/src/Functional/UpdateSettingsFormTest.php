<?php

declare(strict_types=1);

namespace Drupal\Tests\update\Functional;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\update\DataSharing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the update_settings form.
 */
#[Group('update')]
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class UpdateSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['update'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the update_settings form.
   */
  public function testUpdateSettingsForm(): void {
    $url = Url::fromRoute('update.settings');

    // Users without the appropriate permissions should not be able to access.
    $this->drupalGet($url);
    $this->assertSession()->pageTextContains('Access denied');

    // Users with permission should be able to access the form.
    $permissions = ['administer site configuration'];
    $account = $this->setUpCurrentUser([
      'name' => 'system_admin',
      'pass' => 'adminPass',
    ], $permissions);
    $this->drupalLogin($account);
    $this->drupalGet($url);
    $this->assertSession()->fieldExists('update_notify_emails');

    $values_to_enter = [
      'https://example.com',
      'sofie@example.com',
      'https://example.com/also-not-an-email-address',
      'dries@example.com',
    ];

    // Fill in `https://example.com` as the email address to notify. We expect
    // this to trigger a validation error, because it's not an email address,
    // and for the corresponding form item to be highlighted.
    $this->assertSession()->fieldExists('update_notify_emails')->setValue($values_to_enter[0]);
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->statusMessageNotExists(MessengerInterface::TYPE_STATUS);
    $this->assertSession()->statusMessageNotExists(MessengerInterface::TYPE_WARNING);
    $this->assertSession()->statusMessageContains('"https://example.com" is not a valid email address.', MessengerInterface::TYPE_ERROR);
    $this->assertTrue($this->assertSession()->fieldExists('update_notify_emails')->hasClass('error'));
    $this->assertSame([], $this->config('update.settings')->get('notification.emails'));

    // Next, set an invalid email addresses, but make sure it's second entry.
    $this->assertSession()->fieldExists('update_notify_emails')->setValue(implode("\n", array_slice($values_to_enter, 1, 2)));
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->statusMessageNotExists(MessengerInterface::TYPE_STATUS);
    $this->assertSession()->statusMessageNotExists(MessengerInterface::TYPE_WARNING);
    $this->assertSession()->statusMessageContains('"https://example.com/also-not-an-email-address" is not a valid email address.', MessengerInterface::TYPE_ERROR);
    $this->assertTrue($this->assertSession()->fieldExists('update_notify_emails')->hasClass('error'));
    $this->assertSame([], $this->config('update.settings')->get('notification.emails'));

    // Next, set multiple invalid email addresses, and assert the same as above
    // except the message should be adjusted now.
    $this->assertSession()->fieldExists('update_notify_emails')->setValue(implode("\n", $values_to_enter));
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->statusMessageNotExists(MessengerInterface::TYPE_STATUS);
    $this->assertSession()->statusMessageNotExists(MessengerInterface::TYPE_WARNING);
    $this->assertSession()->statusMessageContains('https://example.com, https://example.com/also-not-an-email-address are not valid email addresses.', MessengerInterface::TYPE_ERROR);
    $this->assertTrue($this->assertSession()->fieldExists('update_notify_emails')->hasClass('error'));
    $this->assertSame([], $this->config('update.settings')->get('notification.emails'));

    // Now fill in valid email addresses, now the form should be saved
    // successfully.
    $this->assertSession()->fieldExists('update_notify_emails')->setValue("$values_to_enter[1]\r\n$values_to_enter[3]");
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->statusMessageContains('The configuration options have been saved.', MessengerInterface::TYPE_STATUS);
    $this->assertSession()->statusMessageNotExists(MessengerInterface::TYPE_WARNING);
    $this->assertSession()->statusMessageNotExists(MessengerInterface::TYPE_ERROR);
    $this->assertFalse($this->assertSession()->fieldExists('update_notify_emails')->hasClass('error'));
    $this->assertSame(['sofie@example.com', 'dries@example.com'], $this->config('update.settings')->get('notification.emails'));

    // Fill with an empty value to make sure it's saved as an empty array.
    $this->assertSession()->fieldExists('update_notify_emails')->setValue("");
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->statusMessageContains('The configuration options have been saved.', MessengerInterface::TYPE_STATUS);
    $this->assertSession()->statusMessageNotExists(MessengerInterface::TYPE_WARNING);
    $this->assertSession()->statusMessageNotExists(MessengerInterface::TYPE_ERROR);
    $this->assertSame([], $this->config('update.settings')->get('notification.emails'));

  }

  /**
   * Tests the data sharing settings.
   */
  public function testDataSharing(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer site configuration']));
    $this->drupalGet('admin/reports/updates/settings');
    $this->assertSession()->checkboxChecked('update_share_domain');
    $this->assertSession()->checkboxNotChecked('update_share_site_mail');
    $this->assertSession()->elementTextContains('css', '#edit-update-share-domain--description', parse_url($this->baseUrl, PHP_URL_HOST));
    $this->assertSession()->elementTextContains('css', '#edit-update-share-site-mail--description', 'simpletest@example.com');

    $this->assertSession()->statusMessageNotExists(MessengerInterface::TYPE_WARNING);
    $this->drupalGet('admin/config/system/site-information');
    $this->assertSession()->statusMessageContains('Choose what this site shares with Drupal.org', MessengerInterface::TYPE_WARNING);
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->pageTextContains('Data sharing with Drupal.org');
    $this->assertSession()->pageTextContains('Not yet reviewed');

    \Drupal::state()->set(DataSharing::NOTICE_START_STATE_KEY, time() - DataSharing::NOTICE_PERIOD - 1);
    $this->drupalGet('admin/config/system/site-information');
    $this->assertSession()->statusMessageNotExists(MessengerInterface::TYPE_WARNING);
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->pageTextContains('Recommended settings in effect since');

    $this->drupalGet('admin/reports/updates/settings');
    $this->assertSession()->pageTextContains('The settings shown have applied since');
    $this->submitForm([], 'Save configuration');
    $this->assertTrue($this->config('update.settings')->get('data_sharing.domain'));
    $this->assertFalse($this->config('update.settings')->get('data_sharing.site_mail'));
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->pageTextNotContains('Recommended settings in effect since');
  }

}
