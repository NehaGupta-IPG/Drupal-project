<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Drupal\default_admin\Helper;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies rendered accent and interactive contrast in a real browser.
 *
 * The dark-mode accent values are produced by oklch() colour maths in CSS,
 * so a pure-PHP unit check cannot resolve them. This test enables dark mode,
 * loads each accent preset, and reads the *computed* colour values from the
 * browser (which honours the .gin--dark-mode colour-scheme), then asserts
 * WCAG 2.2 AA ratios. Also covers link / focus / hover which previously
 * regressed to #0029a5 on #2d3755 (1.04:1) on Light/Auto.
 *
 * Runs wherever a WebDriver browser is available (CI, Guidepup).
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class AccentColorContrastJavascriptTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'toolbar',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

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

    $admin_user = $this->drupalCreateUser([
      'access administration pages',
      'administer themes',
      'access toolbar',
      'access content overview',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Each accent preset must keep >= 4.5:1 WCAG in dark mode.
   */
  public function testDarkModeAccentContrast(): void {
    $config = $this->config('default_admin.settings');
    $config->set('enable_dark_mode', '1')->save();

    $presets = Helper::accentColors();
    foreach ($presets as $name => $info) {
      $config->set('preset_accent_color', $name)->save();
      $this->drupalGet('/admin/content');
      $this->assertSession()->statusCodeEquals(200);

      $ratios = $this->getSession()->evaluateScript($this->contrastScript());

      $this->assertGreaterThanOrEqual(
        4.5,
        $ratios['fgOnBg'],
        sprintf('Dark mode: accent "%s" foreground on the dark background must be >= 4.5:1 WCAG (got %.2f:1).', $name, $ratios['fgOnBg'])
      );
      $this->assertGreaterThanOrEqual(
        4.5,
        $ratios['whiteOnFill'],
        sprintf('Dark mode: white label on the accent "%s" button must be >= 4.5:1 WCAG (got %.2f:1).', $name, $ratios['whiteOnFill'])
      );
    }
  }

  /**
   * Links, focus and hover must remain visible on every surface and appearance.
   *
   * Covers Light (0), Dark (1) and Auto. Previously Light/Auto regressed to
   * #0029a5 on #2d3755 (1.04:1). Link needs 4.5:1, focus ring 3:1.
   */
  public function testInteractiveContrast(): void {
    $cases = [
      'Light' => 0,
      'Dark' => 1,
      'Auto' => 'auto',
    ];
    foreach ($cases as $label => $mode) {
      $this->config('default_admin.settings')->set('enable_dark_mode', $mode)->save();
      $this->drupalGet('/admin/appearance/settings/default_admin');
      $this->assertSession()->statusCodeEquals(200);
      $ratios = $this->getSession()->evaluateScript($this->interactiveScript());
      $this->assertGreaterThanOrEqual(4.5, $ratios['linkOnBg'], sprintf('%s: link on bg must be >=4.5:1 (got %.2f:1).', $label, $ratios['linkOnBg']));
      $this->assertGreaterThanOrEqual(4.5, $ratios['linkOnSurface'], sprintf('%s: link on surface must be >=4.5:1 (got %.2f:1).', $label, $ratios['linkOnSurface']));
      $this->assertGreaterThanOrEqual(3.0, $ratios['focusOnBg'], sprintf('%s: focus on bg must be >=3:1 (got %.2f:1).', $label, $ratios['focusOnBg']));
      $this->assertGreaterThanOrEqual(3.0, $ratios['focusOnSurface'], sprintf('%s: focus on surface must be >=3:1 (got %.2f:1).', $label, $ratios['focusOnSurface']));
      $this->assertGreaterThanOrEqual(4.5, $ratios['hoverOnBg'], sprintf('%s: hover (accent-fg) on bg must be >=4.5:1 (got %.2f:1).', $label, $ratios['hoverOnBg']));
      $this->assertGreaterThanOrEqual(4.5, $ratios['activeOnBg'], sprintf('%s: active (dark foreground-safe mix) on bg must be >=4.5:1 (got %.2f:1).', $label, $ratios['activeOnBg']));
    }
  }

  /**
   * High-contrast must approach AAA 7:1 for all 6 modes × 11 presets.
   *
   * Light HC accent-fg is l*0.80 (7.x on white), dark HC is max(l*1.5,0.93) c*1.2.
   * Text/border become pure #000/#fff (was 0.8). Covers the “thin white outline”
   * regression where HC was invisible in dark.
   */
  public function testHighContrastAccent(): void {
    $presets = Helper::accentColors();
    foreach (['Light' => 0, 'Dark' => 1] as $label => $mode) {
      $this->config('default_admin.settings')->set('enable_dark_mode', $mode)->set('high_contrast_mode', 1)->save();
      foreach ($presets as $name => $info) {
        if ($name === 'custom') {
          continue;
        }
        $this->config('default_admin.settings')->set('preset_accent_color', $name)->save();
        $this->drupalGet('/admin/content');
        $this->assertSession()->statusCodeEquals(200);
        $ratios = $this->getSession()->evaluateScript($this->contrastScript());
        $expected = 7.0;
        $this->assertGreaterThanOrEqual($expected, $ratios['fgOnBg'], sprintf('HC %s: accent "%s" fg on bg must be >=%s:1 (got %.2f:1).', $label, $name, $expected, $ratios['fgOnBg']));
      }
    }
    // Reset.
    $this->config('default_admin.settings')->set('high_contrast_mode', 0)->save();
  }

  /**
   * Script for interactive tokens (link / focus / hover) on bg and surface.
   */
  protected function interactiveScript(): string {
    return <<<'JS'
(function () {
  const temp = document.createElement('div');
  temp.style.display = 'none';
  document.body.appendChild(temp);
  function resolve(varName, property) {
    temp.style[property] = 'var(' + varName + ')';
    const v = getComputedStyle(temp)[property];
    temp.style[property] = '';
    return v;
  }
  function lum(s) {
    const m = s.match(/rgba?\(([^)]+)\)/);
    if (!m) return 0;
    const p = m[1].split(',').map(x => parseFloat(x.trim()) / 255);
    const f = x => x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4);
    return 0.2126 * f(p[0]) + 0.7152 * f(p[1]) + 0.0722 * f(p[2]);
  }
  function ratio(a,b){ const A=Math.max(a,b), B=Math.min(a,b); return (A+0.05)/(B+0.05); }
  const link = lum(resolve('--admin-color-link','color'));
  const hover = lum(resolve('--admin-color-link-hover','color'));
  const active = lum(resolve('--admin-color-link-active','color'));
  const focus = lum(resolve('--admin-color-focus','color'));
  const bg = lum(resolve('--admin-color-bg','backgroundColor'));
  const surface = lum(resolve('--admin-color-bg-surface','backgroundColor'));
  return {
    linkOnBg: ratio(link, bg),
    linkOnSurface: ratio(link, surface),
    focusOnBg: ratio(focus, bg),
    focusOnSurface: ratio(focus, surface),
    hoverOnBg: ratio(hover, bg),
    activeOnBg: ratio(active, bg)
  };
})()
JS;
  }

  /**
   * In-page script that resolves the accent CSS variables and returns WCAG ratios.
   *
   * Custom properties are not resolved by getPropertyValue(), so each variable
   * is applied to a throwaway element and its used colour is read back. Because
   * .gin--dark-mode sets color-scheme: dark, light-dark() resolves to the dark
   * value, exactly as a real user sees it.
   */
  protected function contrastScript(): string {
    return <<<'JS'
(function () {
  const temp = document.createElement('div');
  temp.style.display = 'none';
  document.body.appendChild(temp);

  function resolve(varName, property) {
    temp.style[property] = 'var(' + varName + ')';
    const value = getComputedStyle(temp)[property];
    temp.style[property] = '';
    return value;
  }
  function parseRGBFloat(s) {
    const m = s.match(/rgba?\(([^)]+)\)/);
    if (!m) return null;
    const p = m[1].split(',').map(x => parseFloat(x.trim()) / 255);
    const f = x => x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4);
    return 0.2126 * f(p[0]) + 0.7152 * f(p[1]) + 0.0722 * f(p[2]);
  }
  function ratio(l1, l2) {
    const a = Math.max(l1, l2), b = Math.min(l1, l2);
    return (a + 0.05) / (b + 0.05);
  }

  const fgLum = parseRGBFloat(resolve('--admin-color-accent-fg', 'color'));
  const fillLum = parseRGBFloat(resolve('--admin-color-accent-base', 'backgroundColor'));
  const bgLum = parseRGBFloat(resolve('--admin-color-bg', 'backgroundColor'));
  const txtLum = parseRGBFloat(resolve('--admin-color-text-on-primary', 'color'));

  return {
    fgOnBg: ratio(fgLum, bgLum),
    whiteOnFill: ratio(txtLum, fillLum)
  };
})()
JS;
  }

  /**
   * Representative rendered text/surface pairs reach WCAG AA in each appearance.
   *
   * Reads computed colours from the browser — which resolves light-dark() for
   * the active appearance — and measures WCAG ratios. This avoids duplicating
   * the CSS colour formulas in the test. Covers Light, Dark and Auto.
   */
  public function testRenderedSurfaceContrast(): void {
    $cases = [
      'Light' => '0',
      'Dark' => '1',
      'Auto' => 'auto',
    ];
    $config = $this->config('default_admin.settings');
    $config->set('preset_accent_color', 'blue')->save();

    foreach ($cases as $label => $mode) {
      $config->set('enable_dark_mode', $mode)->save();
      $this->drupalGet('/admin/content');
      $this->assertSession()->statusCodeEquals(200);
      $ratios = $this->getSession()->evaluateScript($this->surfaceScript());
      $this->assertGreaterThanOrEqual(4.5, $ratios['textOnBg'], sprintf('%s: page text on main background must be >=4.5:1 (got %.2f:1).', $label, $ratios['textOnBg']));
      $this->assertGreaterThanOrEqual(4.5, $ratios['textOnSurface'], sprintf('%s: text on surface must be >=4.5:1 (got %.2f:1).', $label, $ratios['textOnSurface']));
      $this->assertGreaterThanOrEqual(4.5, $ratios['textOnNested'], sprintf('%s: text on nested surface must be >=4.5:1 (got %.2f:1).', $label, $ratios['textOnNested']));
      $this->assertGreaterThanOrEqual(4.5, $ratios['textOnRaised'], sprintf('%s: text on raised surface must be >=4.5:1 (got %.2f:1).', $label, $ratios['textOnRaised']));
      $this->assertGreaterThanOrEqual(4.5, $ratios['accentFgOnSurface'], sprintf('%s: accent foreground on surface must be >=4.5:1 (got %.2f:1).', $label, $ratios['accentFgOnSurface']));
    }
  }

  /**
   * The active appearance actually resolves the correct light-dark() branch.
   *
   * Proves state selection before any contrast math: Dark must resolve the dark
   * branch of representative semantic variables; Light must resolve the light
   * branch. This catches cascade/state failures where a variable never switches.
   */
  public function testLightDarkStateSelection(): void {
    $config = $this->config('default_admin.settings');
    $config->set('preset_accent_color', 'blue')->save();

    // Dark mode must resolve the dark branch of --admin-color-bg (#1b1b1d).
    $config->set('enable_dark_mode', '1')->save();
    $this->drupalGet('/admin/content');
    $this->assertSession()->statusCodeEquals(200);
    $darkBg = $this->resolveColor('--admin-color-bg');
    $this->assertEquals('rgb(27, 27, 29)', $darkBg, 'Dark mode must resolve --admin-color-bg to the dark branch rgb(27, 27, 29).');
    $this->assertEquals('rgb(42, 42, 45)', $this->resolveColor('--admin-color-bg-surface'), 'Dark mode must resolve --admin-color-bg-surface to the dark branch rgb(42, 42, 45).');

    // Light mode must not resolve the dark branch.
    $config->set('enable_dark_mode', '0')->save();
    $this->drupalGet('/admin/content');
    $this->assertSession()->statusCodeEquals(200);
    $lightBg = $this->resolveColor('--admin-color-bg');
    $this->assertNotEquals('rgb(27, 27, 29)', $lightBg, 'Light mode must not resolve --admin-color-bg to the dark branch.');
    $this->assertNotEquals('rgb(42, 42, 45)', $this->resolveColor('--admin-color-bg-surface'), 'Light mode must not resolve --admin-color-bg-surface to the dark branch.');
  }

  /**
   * Resolves and returns a single computed CSS variable's used colour.
   */
  protected function resolveColor(string $var): string {
    $script = '(() => { const t = document.createElement("div"); t.style.display = "none"; document.body.appendChild(t); t.style.backgroundColor = `var(' . $var . ')`; let c = getComputedStyle(t).backgroundColor; c = c || t.style.backgroundColor; t.remove(); return c; })()';
    try {
      return (string) $this->getSession()->evaluateScript($script);
    }
    catch (\Exception $e) {
      return '';
    }
  }

  /**
   * Script that resolves text / surface / accent pairs and returns WCAG ratios.
   *
   * Custom properties are not resolved by getPropertyValue(), so each variable
   * is applied to a throwaway element and its used colour is read back. The
   * active color-scheme selects the matching light-dark() branch.
   */
  protected function surfaceScript(): string {
    return <<<'JS'
(function () {
  const temp = document.createElement('div');
  temp.style.display = 'none';
  document.body.appendChild(temp);
  function resolve(varName, property) {
    temp.style[property] = 'var(' + varName + ')';
    const v = getComputedStyle(temp)[property];
    temp.style[property] = '';
    return v;
  }
  function lum(s) {
    const m = s.match(/rgba?\(([^)]+)\)/);
    if (!m) return 0;
    const p = m[1].split(',').map(x => parseFloat(x.trim()) / 255);
    const f = x => x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4);
    return 0.2126 * f(p[0]) + 0.7152 * f(p[1]) + 0.0722 * f(p[2]);
  }
  function ratio(a, b) { const A = Math.max(a, b), B = Math.min(a, b); return (A + 0.05) / (B + 0.05); }
  const text = lum(resolve('--admin-color-text', 'color'));
  const acfg = lum(resolve('--admin-color-accent-fg', 'color'));
  const bg = lum(resolve('--admin-color-bg', 'backgroundColor'));
  const surface = lum(resolve('--admin-color-bg-surface', 'backgroundColor'));
  const nested = lum(resolve('--admin-color-bg-surface-nested', 'backgroundColor'));
  const raised = lum(resolve('--admin-color-bg-raised', 'backgroundColor'));
  return {
    textOnBg: ratio(text, bg),
    textOnSurface: ratio(text, surface),
    textOnNested: ratio(text, nested),
    textOnRaised: ratio(text, raised),
    accentFgOnSurface: ratio(acfg, surface)
  };
})()
JS;
  }

}
