<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Kernel;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormState;
use Drupal\default_admin\Helper;
use Drupal\default_admin\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the theme settings and the overrides individual users may store.
 *
 * @see \Drupal\default_admin\Settings
 * @see \Drupal\default_admin\Helper::accentColors()
 * @see \Drupal\default_admin\Helper::accentRadios()
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class SettingsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * The settings service of the theme.
   */
  protected Settings $settings;

  /**
   * The user data service, where the user overrides are stored.
   */
  protected UserDataInterface $userData;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'user']);

    // Installing the theme rebuilds the container, so the reference held by
    // the test has to be refreshed before any service is taken from it.
    $this->container->get('theme_installer')->install(['default_admin']);
    $this->container = \Drupal::getContainer();

    // The settings are read from the administration theme, which is resolved
    // from the system theme configuration.
    $this->config('system.theme')
      ->set('admin', 'default_admin')
      ->set('default', 'default_admin')
      ->save();

    $this->userData = $this->container->get('user.data');
    // This is how the theme itself obtains the service.
    // @see \Drupal\default_admin\Settings::getInstance()
    $this->settings = \Drupal::classResolver(Settings::class);
  }

  /**
   * Tests that the defaults are the settings of the administration theme.
   */
  public function testGetDefaultReadsAdminThemeSettings(): void {
    $this->assertSame('blue', $this->settings->getDefault('preset_accent_color'));
    $this->assertSame('auto', $this->settings->getDefault('enable_dark_mode'));
    $this->assertSame('default', $this->settings->getDefault('preset_focus_color'));
    $this->assertFalse($this->settings->getDefault('high_contrast_mode'));

    // Changed theme settings are picked up instead of being read only once.
    $this->config('default_admin.settings')
      ->set('preset_accent_color', 'teal')
      ->save();

    $this->assertSame('teal', $this->settings->getDefault('preset_accent_color'));
  }

  /**
   * Tests that the settings are read from the administration theme.
   */
  public function testSettingsAreReadFromAdminTheme(): void {
    // Without an administration theme the default theme provides them.
    $this->config('system.theme')
      ->set('admin', '')
      ->save();

    $this->assertSame('blue', $this->settings->getDefault('preset_accent_color'));

    // Installing a theme rebuilds the container, so the service has to be
    // taken from the new one.
    $this->container->get('theme_installer')->install(['stark']);
    $this->container = \Drupal::getContainer();
    $this->settings = \Drupal::classResolver(Settings::class);

    // The administration theme takes precedence over the default theme, and
    // another theme knows nothing about the settings of this one.
    $this->config('system.theme')
      ->set('admin', 'stark')
      ->save();

    $this->assertNull($this->settings->getDefault('preset_accent_color'));
  }

  /**
   * Tests that overriding is only allowed when the theme allows it.
   */
  public function testAllowUserOverridesFollowsThemeSetting(): void {
    $this->assertFalse($this->settings->allowUserOverrides());

    $this->config('default_admin.settings')
      ->set('show_user_theme_settings', TRUE)
      ->save();

    $this->assertTrue($this->settings->allowUserOverrides());
  }

  /**
   * Tests that stored overrides are ignored while the theme forbids them.
   */
  public function testUserOverrideEnabledRequiresThemeSetting(): void {
    $account = $this->createUser();
    // The user has opted in and has a stored override, which happens when the
    // theme setting is switched off again afterwards.
    $this->userData->set('default_admin', $account->id(), 'enable_user_settings', TRUE);
    $this->userData->set('default_admin', $account->id(), 'settings', ['enable_dark_mode' => '1']);

    $this->assertFalse($this->settings->userOverrideEnabled($account));
    $this->assertSame('auto', $this->settings->get('enable_dark_mode', $account));
  }

  /**
   * Tests that overriding requires the user to opt in.
   */
  public function testUserOverrideEnabledRequiresUserOptIn(): void {
    $this->allowOverrides();
    $account = $this->createUser();
    $other_account = $this->createUser();
    $this->settings->setAll(['enable_dark_mode' => '1'], $account);

    $this->assertTrue($this->settings->userOverrideEnabled($account));
    $this->assertFalse($this->settings->userOverrideEnabled($other_account));
  }

  /**
   * Tests that an override only applies to the account it belongs to.
   */
  public function testGetPrefersUserOverrideOverThemeDefault(): void {
    $this->allowOverrides();
    $account = $this->createUser();
    $other_account = $this->createUser();
    $this->settings->setAll(['enable_dark_mode' => '1'], $account);

    $this->assertSame('1', $this->settings->get('enable_dark_mode', $account));
    $this->assertSame('auto', $this->settings->get('enable_dark_mode', $other_account));
    // Settings the user did not override still come from the theme.
    $this->assertSame('default', $this->settings->get('layout_density', $account));
  }

  /**
   * Tests that overrides stored as one item per setting are still read.
   */
  public function testGetReturnsSettingsStoredAsSingleUserDataItems(): void {
    $this->allowOverrides();
    $account = $this->createUser();
    // Overrides used to be stored as one user data item per setting instead
    // of a single item holding all of them.
    $this->userData->set('default_admin', $account->id(), 'enable_user_settings', TRUE);
    $this->userData->set('default_admin', $account->id(), 'preset_accent_color', 'teal');

    $this->assertSame('teal', $this->settings->get('preset_accent_color', $account));
  }

  /**
   * Tests that saving overrides discards the ones stored before.
   */
  public function testSetAllReplacesAllPreviousOverrides(): void {
    $this->allowOverrides();
    $account = $this->createUser();
    $this->userData->set('default_admin', $account->id(), 'enable_user_settings', TRUE);
    $this->userData->set('default_admin', $account->id(), 'settings', ['enable_dark_mode' => '1']);
    $this->userData->set('default_admin', $account->id(), 'preset_accent_color', 'teal');

    $this->settings->setAll(['layout_density' => 'small'], $account);

    $this->assertSame('small', $this->settings->get('layout_density', $account));
    // Neither the previous set of overrides nor the single item stored by an
    // earlier version of the theme survives.
    $this->assertSame('auto', $this->settings->get('enable_dark_mode', $account));
    $this->assertSame('blue', $this->settings->get('preset_accent_color', $account));
  }

  /**
   * Tests that clearing the overrides brings back the theme settings.
   */
  public function testClearRemovesUserOverrides(): void {
    $this->allowOverrides();
    $account = $this->createUser();
    $this->settings->setAll(['enable_dark_mode' => '1'], $account);

    $this->settings->clear($account);

    $this->assertFalse($this->settings->userOverrideEnabled($account));
    $this->assertSame('auto', $this->settings->get('enable_dark_mode', $account));
  }

  /**
   * Tests that the overrides are off without the user data service.
   */
  public function testOverridesAreOffWithoutUserDataService(): void {
    $this->allowOverrides();
    $account = $this->createUser();
    // The user data service belongs to the user module, which a theme cannot
    // depend on, so the service is optional.
    // @see \Drupal\default_admin\Settings::create()
    $settings = new Settings(
      $this->container->get('current_user'),
      $this->container->get('config.factory'),
      NULL,
      $this->container->get(ThemeSettingsProvider::class),
    );

    $this->assertFalse($settings->userOverrideEnabled($account));

    // Storing and clearing overrides is a no-op rather than a failure.
    $settings->setAll(['enable_dark_mode' => '1'], $account);
    $settings->clear($account);

    $this->assertSame([], $this->userData->get('default_admin', $account->id()));
    // So everyone is served the settings of the theme.
    $this->assertSame('auto', $settings->get('enable_dark_mode', $account));
  }

  /**
   * Tests that a setting counts as overridden only if its value differs.
   */
  public function testOverriddenComparesWithThemeDefault(): void {
    $this->allowOverrides();
    $account = $this->createUser();
    $other_account = $this->createUser();
    $this->settings->setAll([
      'enable_dark_mode' => '1',
      'layout_density' => 'default',
    ], $account);

    $this->assertTrue($this->settings->overridden('enable_dark_mode', $account));
    // The user stored the value the theme uses anyway.
    $this->assertFalse($this->settings->overridden('layout_density', $account));
    $this->assertFalse($this->settings->overridden('enable_dark_mode', $other_account));
  }

  /**
   * Tests that the form of an account is filled with the account values.
   */
  public function testGetSettingsFormUsesAccountValues(): void {
    $this->allowOverrides();
    $account = $this->createUser();
    $this->settings->setAll([
      'enable_dark_mode' => '1',
      'preset_accent_color' => 'custom',
      'accent_color' => '#012345',
    ], $account);

    $form = $this->settings->getSettingsForm($account);

    $this->assertSame('1', $form['enable_dark_mode']['#default_value']);
    $this->assertSame('custom', $form['preset_accent_color']['#default_value']);
    $this->assertSame('#012345', $form['accent_color']['#default_value']);
    // The color picker only helps to fill in the text field, so it shows the
    // value of that field.
    $this->assertSame('#012345', $form['accent_group']['accent_picker']['#default_value']);
    // Settings without an override keep the value of the theme.
    $this->assertSame('default', $form['preset_focus_color']['#default_value']);

    // The form of an account is where the overrides are entered, so its
    // elements are not annotated with the override warning.
    foreach ($form as $element) {
      $this->assertNotContains([Settings::class, 'overriddenSettingByUser'], $element['#after_build'] ?? []);
    }
  }

  /**
   * Tests that the theme form shows theme values and marks overrides.
   */
  public function testGetSettingsFormWithoutAccountUsesThemeDefaults(): void {
    $form = $this->settings->getSettingsForm();

    $this->assertSame('auto', $form['enable_dark_mode']['#default_value']);
    $this->assertSame('blue', $form['preset_accent_color']['#default_value']);
    $this->assertSame('', $form['accent_color']['#default_value']);
    $this->assertSame('', $form['accent_group']['accent_picker']['#default_value']);
    $this->assertSame('default', $form['layout_density']['#default_value']);

    // The accent color options are the presets of the helper, and the custom
    // one is what reveals the fields for a color of one's own.
    $this->assertSame(array_keys(Helper::accentColors()), array_keys($form['preset_accent_color']['#options']));
    $this->assertSame(['value' => 'custom'], $form['accent_group']['#states']['visible'][':input[name="preset_accent_color"]']);

    // Every element of the theme form is checked for a user override.
    foreach ($form as $element) {
      $this->assertContains([Settings::class, 'overriddenSettingByUser'], $element['#after_build']);
    }
    // The accent color element keeps the callback of its own as well.
    $this->assertSame([
      [Helper::class, 'accentRadios'],
      [Settings::class, 'overriddenSettingByUser'],
    ], $form['preset_accent_color']['#after_build']);
  }

  /**
   * Tests the warning about an overridden setting on the theme form.
   */
  public function testOverriddenSettingByUserAddsWarning(): void {
    $this->allowOverrides();
    $account = $this->setUpCurrentUser();
    $this->settings->setAll([
      'enable_dark_mode' => '1',
      'layout_density' => 'default',
    ], $account);

    $element = Settings::overriddenSettingByUser($this->appearanceElement());
    $description = (string) $element['#description'];

    // The warning is appended to the description of the element.
    $this->assertStringStartsWith('Enables dark mode for the administration interface.<span class="form-item__warning">', $description);
    // It links to the account the value comes from and names the value the
    // way the form does, not the way it is stored.
    $this->assertStringContainsString('<a href="/user/' . $account->id() . '/edit">current user</a>', $description);
    $this->assertStringContainsString('Appearance: <em class="placeholder">Dark</em>', $description);

    // A setting the user stored the value of the theme for is left alone.
    $density = [
      '#type' => 'radios',
      '#name' => 'layout_density',
      '#title' => 'Layout density',
      '#options' => [
        'default' => 'Default',
        'medium' => 'Compact',
        'small' => 'Narrow',
      ],
    ];

    $this->assertSame($density, Settings::overriddenSettingByUser($density));
  }

  /**
   * Tests that the warning about an overridden setting renders as markup.
   */
  public function testOverriddenSettingByUserWarningRendersAsMarkup(): void {
    $this->allowOverrides();
    $account = $this->setUpCurrentUser();
    $this->settings->setAll(['enable_dark_mode' => '1'], $account);

    $element = Settings::overriddenSettingByUser($this->appearanceElement());

    $this->assertInstanceOf(MarkupInterface::class, $element['#description']);

    // Rendering is what escapes a description that is a plain string, and is
    // how the warning used to reach the screen as the tags it is made of.
    $build = [
      '#type' => 'inline_template',
      '#template' => '{{ description }}',
      '#context' => ['description' => $element['#description']],
    ];
    $rendered = (string) $this->container->get('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('<span class="form-item__warning">', $rendered);
    $this->assertStringContainsString('<a href="/user/' . $account->id() . '/edit">current user</a>', $rendered);
    $this->assertStringNotContainsString('&lt;', $rendered);
  }

  /**
   * Tests that no warning is added while the theme forbids overrides.
   */
  public function testOverriddenSettingByUserIgnoresForbiddenOverrides(): void {
    $account = $this->setUpCurrentUser();
    // The stored override is not in effect, so it must not be announced.
    $this->userData->set('default_admin', $account->id(), 'enable_user_settings', TRUE);
    $this->userData->set('default_admin', $account->id(), 'settings', ['enable_dark_mode' => '1']);
    $element = $this->appearanceElement();

    $this->assertSame($element, Settings::overriddenSettingByUser($element));
  }

  /**
   * Tests that the color picker value is kept out of the submitted values.
   */
  public function testProcessColorPickerDropsHelperValue(): void {
    $element = [
      '#type' => 'color',
      '#parents' => ['accent_group', 'accent_picker'],
    ];
    $form_state = new FormState();
    $clean_value_keys = $form_state->getCleanValueKeys();

    $this->assertSame($element, Settings::processColorPicker($element, $form_state));
    $this->assertSame(array_merge($clean_value_keys, ['accent_group', 'accent_picker']), $form_state->getCleanValueKeys());

    // So the value of the picker is dropped, while the value of the setting
    // it helps to fill in is submitted.
    $form_state->setValues([
      'accent_group' => ['accent_picker' => '#012345'],
      'accent_color' => '#012345',
    ]);
    $form_state->cleanValues();

    $this->assertSame(['accent_color' => '#012345'], $form_state->getValues());
  }

  /**
   * Tests that every accent color preset ends up on its radio button.
   */
  public function testAccentRadiosExposePresetColors(): void {
    $presets = Helper::accentColors();
    // The custom preset is the one the custom color fields depend on.
    $this->assertArrayHasKey('custom', $presets);

    $element = ['#options' => []];
    foreach ($presets as $key => $preset) {
      $element['#options'][$key] = $preset['label'];
      $element[$key]['#return_value'] = $key;
    }

    $element = Helper::accentRadios($element);

    foreach ($presets as $key => $preset) {
      $this->assertSame($key, $element[$key]['#attributes']['data-accent']);
      $this->assertSame('--accent-base: ' . $preset['hex'] . ';', $element[$key]['#attributes']['style']);
    }
  }

  /**
   * Lets users override the theme settings.
   */
  protected function allowOverrides(): void {
    $this->config('default_admin.settings')
      ->set('show_user_theme_settings', TRUE)
      ->save();
  }

  /**
   * Builds the appearance element of the theme settings form.
   *
   * @return array
   *   The element as the form API hands it to an after build callback.
   */
  protected function appearanceElement(): array {
    return [
      '#type' => 'radios',
      '#name' => 'enable_dark_mode',
      '#title' => 'Appearance',
      '#description' => 'Enables dark mode for the administration interface.',
      '#options' => [
        'auto' => 'Auto',
        0 => 'Light',
        1 => 'Dark',
      ],
    ];
  }

}
