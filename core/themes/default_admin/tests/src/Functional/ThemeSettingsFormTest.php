<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the theme settings form and the per user setting overrides.
 *
 * @see \Drupal\default_admin\Hook\FormHooks::formSystemThemeSettingsAlter()
 * @see \Drupal\default_admin\Hook\FormHooks::formUserFormAlter()
 * @see \Drupal\default_admin\Hook\FormHooks::userFormSubmit()
 * @see \Drupal\default_admin\Settings::getSettingsForm()
 * @see \Drupal\default_admin\Settings::overriddenSettingByUser()
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class ThemeSettingsFormTest extends BrowserTestBase {

  /**
   * The form fields the theme adds to theme and user settings forms.
   */
  private const SETTINGS_FIELDS = [
    'enable_dark_mode',
    'preset_accent_color',
    'accent_color',
    'preset_focus_color',
    'focus_color',
    'high_contrast_mode',
    'layout_density',
    'show_description_toggle',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user who may administer the theme settings.
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->assertTrue(\Drupal::service('theme_installer')->install(['default_admin']));
    $this->container->get('config.factory')
      ->getEditable('system.theme')
      ->set('default', 'default_admin')
      ->set('admin', 'default_admin')
      ->save();

    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer themes',
      'access content overview',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests that the extra settings are exposed for this theme only.
   */
  public function testSettingsFormOnlyAltersDefaultAdmin(): void {
    $fields = array_merge(self::SETTINGS_FIELDS, ['show_user_theme_settings']);

    $this->drupalGet('/admin/appearance/settings/default_admin');
    $this->assertSession()->statusCodeEquals(200);
    foreach ($fields as $field) {
      $this->assertSession()->fieldExists($field);
    }

    // Each accent color option carries its own color, so that the options can
    // be previewed in the browser.
    $red_option = 'input[name="preset_accent_color"][value="red"]';
    $this->assertSession()->elementAttributeContains('css', $red_option, 'data-accent', 'red');
    $this->assertSession()->elementAttributeContains('css', $red_option, 'style', '--accent-base: #d8002f;');

    // The settings belong to this theme and must not show up in the settings
    // form of another theme.
    $this->drupalGet('/admin/appearance/settings/stark');
    $this->assertSession()->statusCodeEquals(200);
    foreach ($fields as $field) {
      $this->assertSession()->fieldNotExists($field);
    }
  }

  /**
   * Tests that submitted settings are stored and applied to admin pages.
   */
  public function testSubmitPersistsAndApplies(): void {
    $this->drupalGet('/admin/appearance/settings/default_admin');
    $this->submitForm([
      'enable_dark_mode' => '1',
      'preset_accent_color' => 'custom',
      'accent_color' => '#123456',
      'preset_focus_color' => 'green',
      'high_contrast_mode' => TRUE,
      'layout_density' => 'medium',
      'show_description_toggle' => TRUE,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $settings = $this->config('default_admin.settings');
    $this->assertSame('1', $settings->get('enable_dark_mode'));
    $this->assertSame('custom', $settings->get('preset_accent_color'));
    $this->assertSame('#123456', $settings->get('accent_color'));
    $this->assertSame('green', $settings->get('preset_focus_color'));
    $this->assertTrue($settings->get('high_contrast_mode'));
    $this->assertSame('medium', $settings->get('layout_density'));
    $this->assertTrue($settings->get('show_description_toggle'));

    // The stored settings are applied to every administration page.
    $response = $this->drupalGet('/admin/content');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementAttributeContains('css', 'html', 'data-accent', 'custom');
    $this->assertSession()->elementAttributeContains('css', 'html', 'style', '--accent-base: #123456;');
    $this->assertSession()->elementAttributeContains('css', 'html', 'data-admin-focus', 'green');
    $this->assertSession()->elementAttributeContains('css', 'html', 'data-layout-density', 'medium');
    $this->assertSession()->elementExists('css', 'html.high-contrast-mode');

    // The settings are exposed to JavaScript as well.
    $this->assertStringContainsString('"dark_mode":"1"', $response);
    $this->assertStringContainsString('"preset_accent_color":"custom"', $response);
    $this->assertStringContainsString('"accent_color":"#123456"', $response);
    $this->assertStringContainsString('"high_contrast_mode":true', $response);
  }

  /**
   * Tests that the user form section follows the theme setting.
   */
  public function testUserOverrideSectionVisibility(): void {
    $edit_url = $this->adminUser->toUrl('edit-form');

    // User overrides are not allowed by default.
    $this->drupalGet($edit_url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Default Admin theme settings');
    $this->assertSession()->fieldNotExists('enable_user_settings');
    foreach (self::SETTINGS_FIELDS as $field) {
      $this->assertSession()->fieldNotExists($field);
    }

    $this->config('default_admin.settings')
      ->set('show_user_theme_settings', TRUE)
      ->save();

    $this->drupalGet($edit_url);
    $this->assertSession()->pageTextContains('Default Admin theme settings');
    $this->assertSession()->fieldExists('enable_user_settings');
    foreach (self::SETTINGS_FIELDS as $field) {
      $this->assertSession()->fieldExists($field);
    }

    // Whether users may override the theme settings remains a theme setting.
    $this->assertSession()->fieldNotExists('show_user_theme_settings');
  }

  /**
   * Tests that the overrides of a user apply to that user only.
   */
  public function testUserOverridesApplyPerUser(): void {
    $this->config('default_admin.settings')
      ->set('show_user_theme_settings', TRUE)
      ->save();

    $account = $this->drupalCreateUser([
      'access administration pages',
      'administer themes',
      'access content overview',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet($account->toUrl('edit-form'));
    $this->submitForm([
      'enable_user_settings' => TRUE,
      'enable_dark_mode' => '1',
      'high_contrast_mode' => TRUE,
      'layout_density' => 'small',
    ], 'Save');

    $response = $this->drupalGet('/admin/content');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'html.high-contrast-mode');
    $this->assertSession()->elementAttributeContains('css', 'html', 'data-layout-density', 'small');
    $this->assertStringContainsString('"dark_mode":"1"', $response);

    // The theme settings form points out the overrides of the current user.
    $this->drupalGet('/admin/appearance/settings/default_admin');
    $this->assertSession()->pageTextContains('This setting is overridden by the');

    // Another user still gets the settings configured for the theme.
    $other_account = $this->drupalCreateUser([
      'access administration pages',
      'administer themes',
      'access content overview',
    ]);
    $this->drupalLogin($other_account);
    $response = $this->drupalGet('/admin/content');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', 'html.high-contrast-mode');
    $this->assertSession()->elementAttributeContains('css', 'html', 'data-layout-density', 'default');
    $this->assertStringContainsString('"dark_mode":"auto"', $response);

    $this->drupalGet('/admin/appearance/settings/default_admin');
    $this->assertSession()->pageTextNotContains('This setting is overridden by the');
  }

  /**
   * Tests that disabling the overrides of a user removes them.
   */
  public function testDisablingUserOverridesClears(): void {
    $this->config('default_admin.settings')
      ->set('show_user_theme_settings', TRUE)
      ->save();

    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);
    $edit_url = $account->toUrl('edit-form');

    $this->drupalGet($edit_url);
    $this->submitForm([
      'enable_user_settings' => TRUE,
      'enable_dark_mode' => '1',
      'high_contrast_mode' => TRUE,
    ], 'Save');

    /** @var \Drupal\user\UserDataInterface $user_data */
    $user_data = \Drupal::service('user.data');
    $this->assertNotEmpty($user_data->get('default_admin', $account->id(), 'enable_user_settings'));

    $response = $this->drupalGet($edit_url);
    $this->assertSession()->elementExists('css', 'html.high-contrast-mode');
    $this->assertStringContainsString('"dark_mode":"1"', $response);

    // Disabling the overrides removes all settings stored for the user.
    $this->submitForm(['enable_user_settings' => FALSE], 'Save');
    $this->assertSame([], $user_data->get('default_admin', $account->id()));

    // The theme settings apply again.
    $response = $this->drupalGet($edit_url);
    $this->assertSession()->elementNotExists('css', 'html.high-contrast-mode');
    $this->assertStringContainsString('"dark_mode":"auto"', $response);
  }

}
