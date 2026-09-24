<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the live preview of the theme settings.
 *
 * The behavior under test is migration/js/settings.js, which is attached to
 * the theme settings form and to the user form. It previews a setting while it
 * is being changed, so that the result can be seen before the form is
 * submitted. The preview is applied to the page the form is rendered on, and
 * every assertion below therefore describes a change that happens without
 * reloading anything.
 *
 * The behavior calls into migration/js/accent.js for the colors, so the
 * methods of Drupal.adminAccent the preview relies on are covered here as
 * well. What that file does while it is attached is not covered here.
 *
 * Both form alters add the settings without a form tree, so the element names
 * are the same on both routes.
 *
 * Three defects of the behavior are pointed out where they are observed:
 * - The high contrast mode class is previewed on the body element, while the
 *   rendered page carries it on the root element.
 * - The custom color of the focus color is only applied when the field
 *   already has a value, so selecting the custom preset alone previews
 *   nothing.
 * - The listeners of the focus color preset, of the custom focus color, of
 *   the color picker of the custom focus color and of the high contrast mode
 *   are added without checking that the element exists, so a page rendering
 *   only a part of the settings would lose the whole preview. Both routes
 *   render all four, which is why the preview works on both.
 *
 * @see \Drupal\default_admin\Hook\FormHooks::formSystemThemeSettingsAlter()
 * @see \Drupal\default_admin\Hook\FormHooks::formUserFormAlter()
 * @see \Drupal\default_admin\Settings::getSettingsForm()
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
final class SettingsFormTest extends WebDriverTestBase {

  /**
   * The path of the theme settings form.
   */
  private const SETTINGS_PATH = '/admin/appearance/settings/default_admin';

  /**
   * The color of the 'teal' accent color preset.
   *
   * @see \Drupal\default_admin\Helper::accentColors()
   */
  private const TEAL = '#0e7772';

  /**
   * The color of the 'pink' accent color preset.
   *
   * @see \Drupal\default_admin\Helper::accentColors()
   */
  private const PINK = '#d12f70';

  /**
   * The fallback color of the 'custom' accent color preset.
   *
   * @see \Drupal\default_admin\Helper::accentColors()
   */
  private const CUSTOM_FALLBACK = '#000';

  /**
   * A custom accent color.
   */
  private const ACCENT_COLOR = '#1a2b3c';

  /**
   * Another custom accent color.
   */
  private const OTHER_ACCENT_COLOR = '#4d5e6f';

  /**
   * A custom focus color.
   */
  private const FOCUS_COLOR = '#7a8b9c';

  /**
   * The custom focus color mixed for dark mode.
   *
   * The behavior mixes the color with white, weighted at 65 percent, for the
   * dark mode variant of the style sheet it builds.
   *
   * @see core/themes/default_admin/migration/js/accent.js
   */
  private const FOCUS_COLOR_DARK = '#d0d6dc';

  /**
   * Another custom focus color.
   */
  private const OTHER_FOCUS_COLOR = '#0d1e2f';

  /**
   * {@inheritdoc}
   *
   * The theme ships the block placements of its own regions, so installing
   * the block module renders both forms in the layout of a real site.
   *
   * @see core/themes/default_admin/config/optional
   */
  protected static $modules = ['block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'default_admin';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer themes',
    ]));

    // A fixed wide window keeps the settings clickable regardless of the
    // default window size of the driver.
    $this->getSession()->resizeWindow(1600, 1200);
  }

  /**
   * Tests that the appearance setting toggles the dark mode class.
   *
   * @see core/themes/default_admin/migration/js/settings.js
   */
  public function testDarkModePreview(): void {
    $this->drupalGet(self::SETTINGS_PATH);
    $this->assertPreviewAttached();

    // 'Dark' adds the class the theme exposes to JavaScript to the root
    // element, whatever color scheme the browser prefers.
    $this->clickRadio('enable_dark_mode', '1');
    $this->assertJsCondition('document.documentElement.classList.contains(drupalSettings.defaultAdmin.dark_mode_class)');

    // 'Light' removes it again.
    $this->clickRadio('enable_dark_mode', '0');
    $this->assertJsCondition('!document.documentElement.classList.contains(drupalSettings.defaultAdmin.dark_mode_class)');

    // 'Auto' follows the color scheme the browser prefers. The class is
    // therefore expected to match that preference, which keeps the assertion
    // independent of the preference the test browser happens to have.
    $this->clickRadio('enable_dark_mode', 'auto');
    $this->assertJsCondition('document.documentElement.classList.contains(drupalSettings.defaultAdmin.dark_mode_class) === window.matchMedia("(prefers-color-scheme: dark)").matches');
  }

  /**
   * Tests that the appearance setting applies both colors as well.
   *
   * The handler of the appearance setting reads the accent color and the focus
   * color back from the form and applies them next to the dark mode class,
   * because the colors differ between light and dark mode.
   *
   * @see core/themes/default_admin/migration/js/settings.js
   */
  public function testDarkModePreviewAppliesColors(): void {
    $this->drupalGet(self::SETTINGS_PATH);
    $this->assertPreviewAttached();

    // Choose a preset for both colors, so that the values the handler is
    // expected to read back from the form are known.
    $this->clickRadio('preset_accent_color', 'teal');
    $this->assertAccentBase(self::TEAL);
    $this->getSession()->getPage()->selectFieldOption('preset_focus_color', 'orange');
    $this->assertFocusPreset('orange');

    // Remove both, so that applying them again becomes observable.
    $this->getSession()->executeScript("document.documentElement.style.removeProperty('--accent-base'); document.documentElement.removeAttribute('data-admin-focus');");
    $this->assertJsCondition("document.documentElement.style.getPropertyValue('--accent-base') === '' && document.documentElement.getAttribute('data-admin-focus') === null");

    // Changing the appearance applies the dark mode class and both colors.
    $this->clickRadio('enable_dark_mode', '1');
    $this->assertJsCondition('document.documentElement.classList.contains(drupalSettings.defaultAdmin.dark_mode_class)');
    $this->assertAccentBase(self::TEAL);
    $this->assertFocusPreset('orange');
  }

  /**
   * Tests that the high contrast mode setting is previewed on the body.
   *
   * @see core/themes/default_admin/migration/js/settings.js
   */
  public function testHighContrastModePreview(): void {
    $this->drupalGet(self::SETTINGS_PATH);
    $this->assertPreviewAttached();
    $page = $this->getSession()->getPage();

    $page->checkField('high_contrast_mode');
    $this->assertJsCondition('document.body.classList.contains(drupalSettings.defaultAdmin.high_contrast_mode_class)');

    // This is a defect, not an intended behavior: the rendered page carries
    // the very same class on the root element, so the preview does not show
    // what the saved setting will look like. The setting is off in the
    // installed configuration, so the root element starts without the class
    // and nothing but the preview could have added it.
    // @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessHtml()
    // @see core/themes/default_admin/config/install/default_admin.settings.yml
    $this->assertJsCondition('!document.documentElement.classList.contains(drupalSettings.defaultAdmin.high_contrast_mode_class)');

    $page->uncheckField('high_contrast_mode');
    $this->assertJsCondition('!document.body.classList.contains(drupalSettings.defaultAdmin.high_contrast_mode_class)');
  }

  /**
   * Tests that the accent color preset is previewed.
   *
   * @see core/themes/default_admin/migration/js/settings.js
   */
  public function testAccentColorPresetPreview(): void {
    $this->drupalGet(self::SETTINGS_PATH);
    $this->assertPreviewAttached();
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Every preset is previewed with the color it stands for.
    $this->clickRadio('preset_accent_color', 'teal');
    $this->assertAccentBase(self::TEAL);
    $this->clickRadio('preset_accent_color', 'pink');
    $this->assertAccentBase(self::PINK);

    // The custom preset falls back to the color of its own preset entry as
    // long as the custom color is empty, which is how it is installed.
    // @see core/themes/default_admin/config/install/default_admin.settings.yml
    $this->clickRadio('preset_accent_color', 'custom');
    $this->assertAccentBase(self::CUSTOM_FALLBACK);

    // The custom color is only shown for the custom preset, so it can be
    // filled in now.
    $this->assertNotNull($assert_session->waitForElementVisible('css', 'input[name="accent_color"]'));
    $page->fillField('accent_color', self::ACCENT_COLOR);
    $this->assertAccentBase(self::ACCENT_COLOR);

    // A preset overrides the custom color.
    $this->clickRadio('preset_accent_color', 'teal');
    $this->assertAccentBase(self::TEAL);

    // Returning to the custom preset applies the custom color again, which is
    // the branch of the handler that reads the field.
    $this->clickRadio('preset_accent_color', 'custom');
    $this->assertAccentBase(self::ACCENT_COLOR);
  }

  /**
   * Tests that the two custom accent color fields preview each other.
   *
   * @see core/themes/default_admin/migration/js/settings.js
   */
  public function testAccentColorFieldsStayInSync(): void {
    $this->drupalGet(self::SETTINGS_PATH);
    $this->assertPreviewAttached();
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Both fields are only shown for the custom preset. The text field is
    // empty, which is how it is installed, so it can be typed into without
    // having to clear it first.
    // @see core/themes/default_admin/config/install/default_admin.settings.yml
    $this->clickRadio('preset_accent_color', 'custom');
    $this->assertNotNull($assert_session->waitForElementVisible('css', 'input[name="accent_color"]'));

    // The text field moves the color picker along and previews its color.
    $page->fillField('accent_color', self::ACCENT_COLOR);
    $this->assertInputValue('accent_picker', self::ACCENT_COLOR);
    $this->assertAccentBase(self::ACCENT_COLOR);

    // The color picker fills in the text field and previews its color.
    $this->setColorInput('accent_picker', self::OTHER_ACCENT_COLOR);
    $this->assertInputValue('accent_color', self::OTHER_ACCENT_COLOR);
    $this->assertAccentBase(self::OTHER_ACCENT_COLOR);
  }

  /**
   * Tests that the focus color preset is previewed.
   *
   * @see core/themes/default_admin/migration/js/settings.js
   */
  public function testFocusColorPresetPreview(): void {
    $this->drupalGet(self::SETTINGS_PATH);
    $this->assertPreviewAttached();
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // A preset is previewed as an attribute of the root element, which the
    // style sheets of the theme select the focus color by.
    $page->selectFieldOption('preset_focus_color', 'orange');
    $this->assertFocusPreset('orange');
    $this->assertCustomFocusStyleCount(0);

    // The custom preset marks the root element as well, but previews no color
    // while the custom color is empty, which is how it is installed. That is
    // a defect, not an intended behavior: no style sheet of the theme selects
    // the custom preset, so the page falls back to the focus color of the
    // base variables instead of previewing anything.
    // @see core/themes/default_admin/css/theme/accent.css
    // @see core/themes/default_admin/config/install/default_admin.settings.yml
    $page->selectFieldOption('preset_focus_color', 'custom');
    $this->assertFocusPreset('custom');
    $this->assertCustomFocusStyleCount(0);

    // Filling in a color appends a style sheet to the root element. Its dark
    // mode variant is the very same color mixed with white.
    $this->assertNotNull($assert_session->waitForElementVisible('css', 'input[name="focus_color"]'));
    $page->fillField('focus_color', self::FOCUS_COLOR);
    $this->assertCustomFocusStyle(self::FOCUS_COLOR, self::FOCUS_COLOR_DARK);

    // Choosing a preset again removes that style sheet.
    $page->selectFieldOption('preset_focus_color', 'green');
    $this->assertFocusPreset('green');
    $this->assertCustomFocusStyleCount(0);

    // And the custom preset appends it again, from the field.
    $page->selectFieldOption('preset_focus_color', 'custom');
    $this->assertFocusPreset('custom');
    $this->assertCustomFocusStyle(self::FOCUS_COLOR);
  }

  /**
   * Tests that the two custom focus color fields preview each other.
   *
   * @see core/themes/default_admin/migration/js/settings.js
   */
  public function testFocusColorFieldsStayInSync(): void {
    $this->drupalGet(self::SETTINGS_PATH);
    $this->assertPreviewAttached();
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Both fields are only shown for the custom preset. The text field is
    // empty, which is how it is installed, so it can be typed into without
    // having to clear it first.
    // @see core/themes/default_admin/config/install/default_admin.settings.yml
    $page->selectFieldOption('preset_focus_color', 'custom');
    $this->assertNotNull($assert_session->waitForElementVisible('css', 'input[name="focus_color"]'));

    // The text field moves the color picker along and previews its color.
    $page->fillField('focus_color', self::FOCUS_COLOR);
    $this->assertInputValue('focus_picker', self::FOCUS_COLOR);
    $this->assertCustomFocusStyle(self::FOCUS_COLOR);

    // The color picker fills in the text field and previews its color. Only
    // one style sheet is left behind, because the previous one is removed
    // before the next one is appended.
    $this->setColorInput('focus_picker', self::OTHER_FOCUS_COLOR);
    $this->assertInputValue('focus_color', self::OTHER_FOCUS_COLOR);
    $this->assertCustomFocusStyle(self::OTHER_FOCUS_COLOR);
  }

  /**
   * Tests that the very same preview works on the user form.
   *
   * @see \Drupal\default_admin\Hook\FormHooks::formUserFormAlter()
   */
  public function testPreviewOnUserForm(): void {
    $this->config('default_admin.settings')
      ->set('show_user_theme_settings', TRUE)
      ->save();

    // The settings of a user are theirs to change, so no permission beyond
    // being able to edit their own account is needed.
    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);
    $this->drupalGet($account->toUrl('edit-form'));
    $this->assertPreviewAttached();
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // The user form renders the whole set of settings, under the very same
    // element names as the theme settings form. That is what keeps the
    // preview working here: the listeners of the elements below are added
    // without checking that the element exists, so a route rendering only a
    // part of the settings would lose the preview altogether.
    // All five sit inside the container that stays hidden until the
    // overrides are enabled, and that is deliberate: existence is what the
    // behavior needs, not visibility, so plain existence is all that is
    // asserted for them.
    // @see \Drupal\default_admin\Settings::getSettingsForm()
    $assert_session->elementExists('css', '[data-drupal-selector="edit-preset-accent-color"] input');
    $assert_session->elementExists('css', 'select[name="preset_focus_color"]');
    $assert_session->elementExists('css', 'input[name="focus_picker"]');
    $assert_session->elementExists('css', 'input[name="focus_color"]');
    $assert_session->elementExists('css', 'input[name="high_contrast_mode"]');

    // The settings are hidden until the user enables the overrides. The
    // state:visible handler of the states API toggles the inline display
    // property of the closest form wrapper of the element carrying the
    // condition, which is the container itself here, so the container is
    // what becoming visible can be observed on. No class is toggled. The
    // radios inside it are no signal either: the appearance setting styles
    // its labels and renders its own inputs with an opacity of zero, so
    // they stay invisible to the driver even once the container is shown.
    // @see core/misc/states.js
    // @see core/themes/default_admin/css/components/settings.css
    $page->checkField('enable_user_settings');
    $this->assertNotNull($assert_session->waitForElementVisible('css', '[data-drupal-selector="edit-user-settings"]'));

    // The appearance setting is previewed here as well. Reading the accent
    // color preset and the focus color preset back from the form comes first
    // in that handler, so the dark mode class only appears when both are
    // rendered on this route, too.
    $this->clickRadio('enable_dark_mode', '1');
    $this->assertJsCondition('document.documentElement.classList.contains(drupalSettings.defaultAdmin.dark_mode_class)');
    $this->clickRadio('enable_dark_mode', '0');
    $this->assertJsCondition('!document.documentElement.classList.contains(drupalSettings.defaultAdmin.dark_mode_class)');

    // The high contrast mode is previewed on the body element here as well.
    $page->checkField('high_contrast_mode');
    $this->assertJsCondition('document.body.classList.contains(drupalSettings.defaultAdmin.high_contrast_mode_class)');
  }

  /**
   * Waits until the preview is ready to be operated.
   *
   * The accent behavior marks the root element while the behaviors of the page
   * are attached. That pass is synchronous, so once the marker can be read the
   * preview has bound its listeners as well.
   *
   * @see core/themes/default_admin/migration/js/accent.js
   */
  private function assertPreviewAttached(): void {
    $this->assertJsCondition('document.readyState === "complete"');
    $this->assertNotNull($this->assertSession()->waitForElement('css', 'html[data-once~="adminAccent"]'));
  }

  /**
   * Clicks a radio button of a setting.
   *
   * @param string $name
   *   The name of the setting.
   * @param string $value
   *   The value of the radio button to click.
   */
  private function clickRadio(string $name, string $value): void {
    $selector = sprintf('input[name="%s"][value="%s"]', $name, $value);
    $this->assertSession()->elementExists('css', $selector)->click();
  }

  /**
   * Sets the value of a color input.
   *
   * A color input cannot be typed into, so its value is assigned and the
   * change event the preview listens for is dispatched explicitly.
   *
   * @param string $name
   *   The name of the color input.
   * @param string $value
   *   The color to set.
   */
  private function setColorInput(string $name, string $value): void {
    $this->getSession()->executeScript(sprintf(
      'const input = document.querySelector(\'input[name="%s"]\'); input.value = "%s"; input.dispatchEvent(new Event("change", { bubbles: true }));',
      $name,
      $value,
    ));
  }

  /**
   * Asserts the value of an input of the form.
   *
   * The preview assigns the value property of the input it keeps in sync,
   * which leaves the value attribute of the rendered element untouched.
   *
   * @param string $name
   *   The name of the input.
   * @param string $value
   *   The expected value.
   */
  private function assertInputValue(string $name, string $value): void {
    $this->assertJsCondition(sprintf(
      "document.querySelector('input[name=\"%s\"]').value === '%s'",
      $name,
      $value,
    ));
  }

  /**
   * Asserts the accent color of the page.
   *
   * @param string $color
   *   The expected color.
   */
  private function assertAccentBase(string $color): void {
    $this->assertJsCondition(sprintf(
      "document.documentElement.style.getPropertyValue('--accent-base') === '%s'",
      $color,
    ));
  }

  /**
   * Asserts the focus color preset of the page.
   *
   * @param string $preset
   *   The expected preset.
   */
  private function assertFocusPreset(string $preset): void {
    $this->assertJsCondition(sprintf(
      "document.documentElement.getAttribute('data-admin-focus') === '%s'",
      $preset,
    ));
  }

  /**
   * Asserts the number of style sheets of the custom focus color.
   *
   * @param int $count
   *   The expected number.
   */
  private function assertCustomFocusStyleCount(int $count): void {
    $this->assertJsCondition(sprintf(
      "document.querySelectorAll('html > style.custom-focus').length === %d",
      $count,
    ));
  }

  /**
   * Asserts the custom focus color the page previews.
   *
   * The style sheet the preview appends carries the color of both modes, and
   * the color of dark mode is the mixed one, so the colors tell the two rules
   * apart.
   *
   * @param string $color
   *   The expected color.
   * @param string|null $dark_color
   *   The expected color of dark mode, or NULL to leave it alone.
   */
  private function assertCustomFocusStyle(string $color, ?string $dark_color = NULL): void {
    $this->assertCustomFocusStyleCount(1);
    foreach (array_filter([$color, $dark_color]) as $expected) {
      $this->assertJsCondition(sprintf(
        "document.querySelector('html > style.custom-focus').textContent.includes('--admin-color-focus: %s;')",
        $expected,
      ));
    }
  }

}
