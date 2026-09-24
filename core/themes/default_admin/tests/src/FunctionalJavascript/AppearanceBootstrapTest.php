<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the appearance bootstrap of the theme.
 *
 * Two scripts are attached to every page of the theme and are covered here:
 *
 * - migration/js/init.js runs as native JavaScript in the document head before
 *   anything is painted. It reads the dark mode setting from the JSON script
 *   element the theme renders, restores the stored sidebar width and injects a
 *   style element that suppresses the sidebar transition while the sidebar is
 *   collapsed.
 * - migration/js/accent.js attaches a behavior to the html element that writes
 *   the focus color preset to an attribute and, for a custom focus color,
 *   appends a style element carrying the custom property.
 *
 * A plain administration page is used, because neither script needs a form,
 * and because migration/js/sidebar.js - which removes the injected style
 * element again - is only attached to content forms. That keeps the injected
 * element observable.
 *
 * @see \Drupal\default_admin\Hook\ThemeHooks::pageAttachmentsAlter()
 * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessHtml()
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
final class AppearanceBootstrapTest extends WebDriverTestBase {

  /**
   * The administration page both scripts are attached to.
   */
  private const PAGE = 'admin/config';

  /**
   * The selector of the html element the accent behavior has attached to.
   *
   * The behavior marks the html element through once(), so this is the gate
   * for every assertion about state the behavior writes.
   *
   * @see core/themes/default_admin/migration/js/accent.js
   */
  private const ATTACHED = 'html[data-once~="adminAccent"]';

  /**
   * The class name the dark mode is expressed with.
   *
   * @see core/themes/default_admin/migration/js/init.js
   */
  private const DARK_MODE_CLASS = 'dark-mode';

  /**
   * The browser storage key of the sidebar width.
   */
  private const WIDTH_KEY = 'Drupal.defaultAdmin.sidebarWidth';

  /**
   * The browser storage key of the sidebar state on wide viewports.
   */
  private const DESKTOP_KEY = 'Drupal.defaultAdmin.sidebarExpanded.desktop';

  /**
   * The custom property the stored sidebar width is written to.
   */
  private const WIDTH_PROPERTY = '--admin-theme-sidebar-width';

  /**
   * The custom property the accent color is written to.
   */
  private const ACCENT_PROPERTY = '--accent-base';

  /**
   * The selector of the style element the init script injects.
   */
  private const INLINE_STYLES = 'style.sidebar-inline-styles';

  /**
   * The selector of the style element a custom focus color is carried by.
   */
  private const CUSTOM_FOCUS = 'html > style.custom-focus';

  /**
   * The custom focus color used by the test.
   */
  private const FOCUS_COLOR = '#0088cc';

  /**
   * The dark mode variant the accent script mixes the focus color into.
   *
   * Drupal.adminAccent.mixColor() weights white at 65% per channel and
   * truncates the result: 0x00 -> floor(0 + 255 * 0.65) = 0xa5, 0x88 ->
   * floor(136 + 119 * 0.65) = 0xd5, 0xcc -> floor(204 + 51 * 0.65) = 0xed.
   *
   * @see core/themes/default_admin/migration/js/accent.js
   */
  private const FOCUS_COLOR_DARK = '#a5d5ed';

  /**
   * The viewport width the init script injects its style element below.
   *
   * @see core/themes/default_admin/migration/js/init.js
   */
  private const BREAKPOINT = 1024;

  /**
   * The window width used above that breakpoint.
   */
  private const DESKTOP_WIDTH = 1400;

  /**
   * The window width used below that breakpoint.
   */
  private const MOBILE_WIDTH = 800;

  /**
   * The window height used for both widths.
   */
  private const WINDOW_HEIGHT = 1000;

  /**
   * {@inheritdoc}
   *
   * The appearance bootstrap is attached to every page of the theme, so the
   * administration page used here needs nothing beyond the install profile.
   */
  protected static $modules = [];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'default_admin';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser(['access administration pages']));
  }

  /**
   * Tests the state of an explicitly enabled dark mode.
   *
   * The theme renders the setting into a JSON script element, which the init
   * script parses into a window variable before adding the class.
   *
   * @see \Drupal\default_admin\Hook\ThemeHooks::pageAttachmentsAlter()
   * @see core/themes/default_admin/migration/js/init.js
   */
  public function testDarkModeEnabled(): void {
    // The setting is a string, both in the form that writes it and in the JSON
    // payload, so the window variable is the string as well. The init script
    // compares it loosely against 1.
    $this->setAppearance(['enable_dark_mode' => '1']);
    $this->loadAppearancePage();

    $this->assertJsValue('window.defaultAdminDarkMode', '1');
    $this->assertDarkMode(TRUE);
  }

  /**
   * Tests the state of an explicitly disabled dark mode.
   *
   * @see core/themes/default_admin/migration/js/init.js
   */
  public function testDarkModeDisabled(): void {
    $this->setAppearance(['enable_dark_mode' => '0']);
    $this->loadAppearancePage();

    $this->assertJsValue('window.defaultAdminDarkMode', '0');
    $this->assertDarkMode(FALSE);
  }

  /**
   * Tests that the automatic dark mode follows the preferred color scheme.
   *
   * The automatic setting is the installed default, and it is the only value
   * for which the init script consults the color scheme of the browser. The
   * scheme cannot be emulated through this driver, so the assertion pins the
   * equivalence the init script establishes instead of a fixed class: the
   * class is present exactly while a dark scheme is preferred.
   *
   * @see core/themes/default_admin/config/install/default_admin.settings.yml
   * @see core/themes/default_admin/migration/js/init.js
   */
  public function testAutomaticDarkModeFollowsTheColorScheme(): void {
    $this->assertSame('auto', $this->config('default_admin.settings')->get('enable_dark_mode'));
    $this->loadAppearancePage();

    $this->assertJsValue('window.defaultAdminDarkMode', 'auto');
    $this->assertJsCondition(sprintf(
      'document.documentElement.classList.contains(%s) === window.matchMedia("(prefers-color-scheme: dark)").matches',
      json_encode(self::DARK_MODE_CLASS, JSON_THROW_ON_ERROR),
    ));

    // The class name the init script hard codes is the one the accent script
    // reads from the settings payload, so the two scripts cannot drift apart.
    $this->assertNotNull($this->assertSession()->waitForElement('css', self::ATTACHED));
    $this->assertJsValue('drupalSettings.defaultAdmin.dark_mode_class', self::DARK_MODE_CLASS);
  }

  /**
   * Tests that a stored sidebar width is applied while the page loads.
   *
   * This is the reading half of the sidebar width, which is stored by the drag
   * handle of the sidebar panel on content forms.
   *
   * @see core/themes/default_admin/migration/js/init.js
   */
  public function testStoredSidebarWidthIsApplied(): void {
    // Without a stored width the init script leaves the custom property alone,
    // so the value declared in the style sheets stays in effect.
    $this->loadAppearancePage();
    $this->assertSidebarWidth('');

    $this->loadAppearancePage([self::WIDTH_KEY => '280px']);
    $this->assertSidebarWidth('280px');

    // Any stored value is applied verbatim, the script does not parse it.
    $this->loadAppearancePage([self::WIDTH_KEY => '30rem']);
    $this->assertSidebarWidth('30rem');
  }

  /**
   * Tests the style element injected for a collapsed sidebar.
   *
   * The init script injects the element for a stored collapsed state, and also
   * below the breakpoint, where the sidebar is an overlay regardless of the
   * stored state.
   *
   * @see core/themes/default_admin/migration/js/init.js
   */
  public function testCollapsedSidebarInjectsInlineStyles(): void {
    $inner_width = $this->loadAppearancePage([self::DESKTOP_KEY => 'false']);
    $this->assertGreaterThanOrEqual(self::BREAKPOINT, $inner_width, 'A stored collapsed state injects the style element above the breakpoint as well.');
    $this->assertInlineStyles(TRUE);

    // The element suppresses the sidebar offset and the transition of the page
    // and hides the overlay of the panel.
    $this->assertJsCondition(sprintf(
      'document.querySelector(%s).textContent.includes("--admin-theme-sidebar-offset: 0px")',
      json_encode(self::INLINE_STYLES, JSON_THROW_ON_ERROR),
    ));
    $this->assertJsCondition(sprintf(
      'document.querySelector(%s).textContent.includes(".meta-sidebar__overlay")',
      json_encode(self::INLINE_STYLES, JSON_THROW_ON_ERROR),
    ));

    // It is inserted in front of the first script element of the document, so
    // that it takes effect before any script runs.
    $this->assertJsCondition(sprintf(
      'document.querySelector(%s).nextElementSibling === document.querySelector("script")',
      json_encode(self::INLINE_STYLES, JSON_THROW_ON_ERROR),
    ));

    // Below the breakpoint the stored state is irrelevant.
    $inner_width = $this->loadAppearancePage([self::DESKTOP_KEY => 'true'], self::MOBILE_WIDTH);
    $this->assertLessThan(self::BREAKPOINT, $inner_width, 'The style element is injected below the breakpoint regardless of the stored state.');
    $this->assertInlineStyles(TRUE);
  }

  /**
   * Tests that no style element is injected for an expanded sidebar.
   *
   * @see core/themes/default_admin/migration/js/init.js
   */
  public function testExpandedSidebarSkipsInlineStyles(): void {
    $inner_width = $this->loadAppearancePage([self::DESKTOP_KEY => 'true']);
    $this->assertGreaterThanOrEqual(self::BREAKPOINT, $inner_width, 'A stored expanded state only skips the style element above the breakpoint.');
    $this->assertInlineStyles(FALSE);

    // The whole branch is guarded by the presence of the key, so an untouched
    // browser storage produces no element either.
    $this->loadAppearancePage();
    $this->assertInlineStyles(FALSE);
  }

  /**
   * Tests the focus color preset the accent behavior writes.
   *
   * The attribute is rendered by the preprocess layer and written again by the
   * behavior, so it is asserted here for the value the behavior reads from the
   * settings payload. What only the behavior can produce is the absence of the
   * custom focus style element for a preset other than the custom one.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessHtml()
   * @see core/themes/default_admin/migration/js/accent.js
   */
  public function testFocusColorPreset(): void {
    $this->setAppearance([
      'preset_focus_color' => 'orange',
      // A configured custom color is ignored while a preset is selected.
      'focus_color' => self::FOCUS_COLOR,
    ]);
    $this->loadAppearancePage();

    $this->assertNotNull($this->assertSession()->waitForElement('css', self::ATTACHED));
    $this->assertJsValue('drupalSettings.defaultAdmin.preset_focus_color', 'orange');
    $this->assertJsValue('document.documentElement.getAttribute("data-admin-focus")', 'orange');
    $this->assertCustomFocusStyles(0);
  }

  /**
   * Tests the style element a custom focus color is carried by.
   *
   * Nothing but the accent script produces this element, which makes it the
   * proof that the behavior has run.
   *
   * @see core/themes/default_admin/migration/js/accent.js
   */
  public function testCustomFocusColor(): void {
    $this->setAppearance([
      'preset_focus_color' => 'custom',
      'focus_color' => self::FOCUS_COLOR,
    ]);
    $this->loadAppearancePage();

    $this->assertNotNull($this->assertSession()->waitForElement('css', self::ATTACHED));
    $this->assertJsValue('document.documentElement.getAttribute("data-admin-focus")', 'custom');

    // The element is appended to the html element, and the previous one is
    // removed first, so there is never more than one.
    $this->assertCustomFocusStyles(1);
    $this->assertJsValue(sprintf(
      'document.querySelector(%s).parentElement.tagName',
      json_encode(self::CUSTOM_FOCUS, JSON_THROW_ON_ERROR),
    ), 'HTML');

    // The configured color is used as is for the light appearance, and mixed
    // with white for the dark one.
    $this->assertCustomFocusContains('--admin-color-focus: ' . self::FOCUS_COLOR . ';');
    $this->assertCustomFocusContains('--admin-color-focus: ' . self::FOCUS_COLOR_DARK . ';');
    $this->assertCustomFocusContains('.dark-mode[data-admin-focus="custom"]');
  }

  /**
   * Tests the accent color of a page the accent behavior does not touch.
   *
   * The accent behavior calls checkDarkMode() and setFocusColor() only, so
   * neither setAccentColor() nor setCustomAccentColor() runs outside of the
   * theme settings form. The custom property is rendered by the preprocess
   * layer instead, which is asserted here through the object model, for both
   * branches of the preset.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessHtml()
   * @see \Drupal\default_admin\Helper::accentColors()
   */
  public function testAccentColorIsRenderedByThePreprocessLayer(): void {
    $this->setAppearance(['preset_accent_color' => 'red']);
    $this->loadAppearancePage();
    $this->assertNotNull($this->assertSession()->waitForElement('css', self::ATTACHED));

    // The hex value of the preset. setAccentColor() would read the same value
    // from the settings payload, so switching the preset in the theme settings
    // form cannot make the appearance jump.
    $this->assertAccentColor('#d8002f');
    $this->assertJsCondition(sprintf(
      'drupalSettings.defaultAdmin.accent_colors[drupalSettings.defaultAdmin.preset_accent_color].hex === document.documentElement.style.getPropertyValue(%s)',
      json_encode(self::ACCENT_PROPERTY, JSON_THROW_ON_ERROR),
    ));

    // The custom preset uses the configured color. Its entry of the preset
    // list only carries a fallback, so here the payload value that has to
    // agree with the rendered one is the configured color that
    // setCustomAccentColor() reads.
    $this->setAppearance([
      'preset_accent_color' => 'custom',
      'accent_color' => '#0a7a5a',
    ]);
    $this->loadAppearancePage();
    $this->assertNotNull($this->assertSession()->waitForElement('css', self::ATTACHED));
    $this->assertAccentColor('#0a7a5a');
    $this->assertJsCondition(sprintf(
      'drupalSettings.defaultAdmin.accent_color === document.documentElement.style.getPropertyValue(%s)',
      json_encode(self::ACCENT_PROPERTY, JSON_THROW_ON_ERROR),
    ));
  }

  /**
   * Overrides appearance settings of the theme.
   *
   * The theme settings are read through the theme settings provider, which
   * falls back to the default theme when no administration theme is
   * configured.
   *
   * @param array<string, string> $settings
   *   The settings to write, keyed by setting name.
   *
   * @see \Drupal\default_admin\Settings::get()
   */
  private function setAppearance(array $settings): void {
    $config = $this->config('default_admin.settings');
    foreach ($settings as $name => $value) {
      $config->set($name, $value);
    }
    $config->save();
  }

  /**
   * Loads the administration page with a prepared browser storage.
   *
   * The page is requested twice, because browser storage can only be written
   * once a page of the site is open, and the init script reads it while the
   * document is still being parsed.
   *
   * @param array<string, string> $storage
   *   (optional) Browser storage values to prepare for the page load, keyed by
   *   storage key. The storage is always cleared first.
   * @param int $width
   *   (optional) The window width to set before the page is requested.
   *
   * @return int
   *   The inner width of the window the page was loaded with.
   */
  private function loadAppearancePage(array $storage = [], int $width = self::DESKTOP_WIDTH): int {
    $session = $this->getSession();
    $session->resizeWindow($width, self::WINDOW_HEIGHT);
    $this->drupalGet(self::PAGE);

    $script = 'localStorage.clear();';
    foreach ($storage as $key => $value) {
      $script .= sprintf(
        'localStorage.setItem(%s, %s);',
        json_encode($key, JSON_THROW_ON_ERROR),
        json_encode($value, JSON_THROW_ON_ERROR),
      );
    }
    $session->executeScript($script);
    $session->reload();
    // The init script runs while the document is parsed, and nothing on this
    // page removes what it produces, so waiting for the finished document is
    // enough to observe all of it.
    $this->assertJsCondition('document.readyState === "complete"');

    // The window is not the viewport, and the requested size may have been
    // clamped, so the branch the assertions rely on is measured.
    return (int) $session->evaluateScript('window.innerWidth');
  }

  /**
   * Asserts the value of a JavaScript expression.
   *
   * @param string $expression
   *   The expression to evaluate.
   * @param string $value
   *   The expected value.
   */
  private function assertJsValue(string $expression, string $value): void {
    $this->assertJsCondition(sprintf(
      '%s === %s',
      $expression,
      json_encode($value, JSON_THROW_ON_ERROR),
    ));
  }

  /**
   * Asserts the dark mode class of the document element.
   *
   * The class is added by a script instead of being rendered, so it is read as
   * a property of the element and not through a selector.
   *
   * @param bool $enabled
   *   TRUE if the class is expected to be present, FALSE otherwise.
   */
  private function assertDarkMode(bool $enabled): void {
    $this->assertJsCondition(sprintf(
      'document.documentElement.classList.contains(%s) === %s',
      json_encode(self::DARK_MODE_CLASS, JSON_THROW_ON_ERROR),
      $enabled ? 'true' : 'false',
    ));
  }

  /**
   * Asserts the sidebar width written to the document element.
   *
   * @param string $width
   *   The expected value of the custom property, or an empty string if the
   *   property is expected to be unset.
   */
  private function assertSidebarWidth(string $width): void {
    $this->assertJsValue(sprintf(
      'document.documentElement.style.getPropertyValue(%s)',
      json_encode(self::WIDTH_PROPERTY, JSON_THROW_ON_ERROR),
    ), $width);
  }

  /**
   * Asserts the accent color written to the document element.
   *
   * @param string $color
   *   The expected value of the custom property.
   */
  private function assertAccentColor(string $color): void {
    $this->assertJsValue(sprintf(
      'document.documentElement.style.getPropertyValue(%s)',
      json_encode(self::ACCENT_PROPERTY, JSON_THROW_ON_ERROR),
    ), $color);
  }

  /**
   * Asserts the presence of the style element the init script injects.
   *
   * @param bool $injected
   *   TRUE if the element is expected to be present, FALSE otherwise.
   */
  private function assertInlineStyles(bool $injected): void {
    $this->assertJsCondition(sprintf(
      'document.querySelectorAll(%s).length === %d',
      json_encode(self::INLINE_STYLES, JSON_THROW_ON_ERROR),
      $injected ? 1 : 0,
    ));
  }

  /**
   * Asserts the number of custom focus color style elements.
   *
   * @param int $count
   *   The expected number of elements.
   */
  private function assertCustomFocusStyles(int $count): void {
    $this->assertJsCondition(sprintf(
      'document.querySelectorAll(%s).length === %d',
      json_encode(self::CUSTOM_FOCUS, JSON_THROW_ON_ERROR),
      $count,
    ));
  }

  /**
   * Asserts a declaration of the custom focus color style element.
   *
   * @param string $declaration
   *   The text the element is expected to contain.
   */
  private function assertCustomFocusContains(string $declaration): void {
    $this->assertJsCondition(sprintf(
      'document.querySelector(%s).textContent.includes(%s)',
      json_encode(self::CUSTOM_FOCUS, JSON_THROW_ON_ERROR),
      json_encode($declaration, JSON_THROW_ON_ERROR),
    ));
  }

}
