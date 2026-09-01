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
   * High-contrast must keep accent-foreground legible on main surfaces.
   *
   * High-contrast resolves accent-foreground from the accent base and
   * surfaces text and borders onto the high-contrast ramp. This covers the
   * “thin white outline” regression where HC was unreadable in dark.
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
    if (!CSS.supports('color', s)) {
      throw new Error('Unsupported computed color: ' + s);
    }
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 1;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) {
      throw new Error('Canvas 2D context unavailable while resolving: ' + s);
    }
    ctx.fillStyle = s;
    ctx.fillRect(0, 0, 1, 1);
    const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
    if (a !== 255) {
      throw new Error('Expected opaque computed color, got: ' + s);
    }
    const f = value => {
      const x = value / 255;
      return x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
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
  function lum(s) {
    if (!CSS.supports('color', s)) {
      throw new Error('Unsupported computed color: ' + s);
    }
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 1;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) {
      throw new Error('Canvas 2D context unavailable while resolving: ' + s);
    }
    ctx.fillStyle = s;
    ctx.fillRect(0, 0, 1, 1);
    const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
    if (a !== 255) {
      throw new Error('Expected opaque computed color, got: ' + s);
    }
    const f = value => {
      const x = value / 255;
      return x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  }
  function ratio(l1, l2) {
    const a = Math.max(l1, l2), b = Math.min(l1, l2);
    return (a + 0.05) / (b + 0.05);
  }

  const fgLum = lum(resolve('--admin-color-accent-fg', 'color'));
  const fillLum = lum(resolve('--admin-color-accent-base', 'backgroundColor'));
  const bgLum = lum(resolve('--admin-color-bg', 'backgroundColor'));
  const txtLum = lum(resolve('--admin-color-text-on-primary', 'color'));

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
   * Every focus preset keeps >= 3:1 against the main surfaces, rendered.
   *
   * --admin-color-focus is switched by data-gin-focus on the element, so each
   * preset is measured with the matching attribute in place and the active
   * appearance resolving the correct light-dark() branch.
   */
  public function testFocusPresetRendered(): void {
    $focusPresets = ['blue', 'green', 'amber', 'neutral'];
    $cases = ['Light' => 0, 'Dark' => 1, 'Auto' => 'auto'];
    $this->config('default_admin.settings')->set('preset_accent_color', 'blue')->save();
    foreach ($cases as $caseLabel => $mode) {
      $this->config('default_admin.settings')->set('enable_dark_mode', $mode)->save();
      $this->drupalGet('/admin/content');
      $this->assertSession()->statusCodeEquals(200);
      foreach ($focusPresets as $preset) {
        $ratios = $this->getSession()->evaluateScript($this->focusPresetScript($preset));
        foreach (['bg', 'surface', 'nested', 'raised', 'input'] as $surface) {
          $this->assertGreaterThanOrEqual(
            3.0,
            $ratios[$surface],
            sprintf('%s: focus preset "%s" on %s must be >=3:1 (got %.2f:1).', $caseLabel, $preset, $surface, $ratios[$surface])
          );
        }
      }
    }
  }

  /**
   * A meaningful icon stays >= 3:1 on the icon link in each state.
   *
   * The icon is drawn with currentColor and only its container background
   * changes across rest / hover / active. Active uses an accent fill, so the
   * icon must flip to a contrasting colour (white light, near-black dark).
   */
  public function testMeaningfulIconStateContrast(): void {
    $cases = ['Light' => 0, 'Dark' => 1];
    $this->config('default_admin.settings')->set('preset_accent_color', 'blue')->save();
    foreach ($cases as $caseLabel => $mode) {
      $this->config('default_admin.settings')->set('enable_dark_mode', $mode)->save();
      $this->drupalGet('/admin/content');
      $this->assertSession()->statusCodeEquals(200);
      $ratios = $this->getSession()->evaluateScript($this->iconStateScript());
      foreach (['rest', 'hover', 'active'] as $state) {
        $this->assertGreaterThanOrEqual(
          3.0,
          $ratios[$state],
          sprintf('%s: icon on %s background must be >=3:1 (got %.2f:1).', $caseLabel, $state, $ratios[$state])
        );
      }
    }
  }

  /**
   * Rendered interaction states keep text/icon contrast for every fixed accent.
   *
   * Renders real control fixtures so the production stylesheets apply, reads
   * the *computed* foreground and *painted* background (compositing alpha
   * through ancestors), and asserts WCAG thresholds per state. WebDriver cannot
   * force :hover/:active on a real node, so those state colours are resolved
   * through the production semantic token that rule maps to, on a real element
   * in the active appearance. Pseudo-states that can be established (focus)
   * are triggered directly. The browser — not a PHP re-derivation — resolves
   * every colour.
   *
   * States asserted: link rest/hover/active/focus (text >= 4.5:1); secondary
   * button rest/hover/active (text >= 4.5:1); primary (accent-filled) button
   * rest/hover/active/focus (text-on-fill >= 4.5:1); Details summary
   * closed/hover/open/open+hover (text >= 4.5:1, disclosure marker >= 3:1).
   * Auto is compared to its explicit equivalent.
   */
  public function testRenderedInteractionStates(): void {
    $presets = Helper::accentColors();
    unset($presets['custom']);
    if (isset($presets['neutral'])) {
      // Neutral remains in the fixed set but is achromatic; it still runs.
    }
    $cases = [
      'Light' => ['mode' => '0', 'dark' => false, 'group' => 'explicit'],
      'Dark' => ['mode' => '1', 'dark' => true, 'group' => 'explicit'],
      'Auto-light' => ['mode' => 'auto', 'dark' => false, 'group' => 'auto'],
      'Auto-dark' => ['mode' => 'auto', 'dark' => true, 'group' => 'auto'],
    ];
    $byMode = [];
    foreach ($cases as $caseLabel => $case) {
      $branch = $case['dark'] ? 'dark' : 'light';
      $this->config('default_admin.settings')
        ->set('enable_dark_mode', $case['mode'])
        ->set('preset_accent_color', 'blue')
        ->save();
      $this->drupalGet('/admin/appearance/settings/default_admin');
      $this->assertSession()->statusCodeEquals(200);
      foreach ($presets as $name => $info) {
        $this->config('default_admin.settings')->set('preset_accent_color', $name)->save();
        $this->drupalGet('/admin/appearance/settings/default_admin');
        $this->assertSession()->statusCodeEquals(200);
        $measured = $this->getSession()->evaluateScript($this->renderedInteractionScript($case['dark']));
        $this->assertStates($caseLabel, $name, $measured);
        // Underlined rest links resolve the same accent text colour regardless
        // of whether the appearance is forced or follows the system, so Auto
        // cannot drift from Light or Dark.
        $byMode[$branch][$case['group']][$name] = $measured['link']['rest']['fg'];
      }
      $this->config('default_admin.settings')->set('preset_accent_color', 'blue')->save();
    }
    foreach ($presets as $name => $info) {
      foreach (['light', 'dark'] as $branch) {
        $this->assertEquals(
          $byMode[$branch]['explicit'][$name],
          $byMode[$branch]['auto'][$name],
          sprintf('Auto-%s link for "%s" must equal explicit (got %s vs %s).', $branch, $name, $byMode[$branch]['explicit'][$name], $byMode[$branch]['auto'][$name])
        );
      }
    }
  }

  /**
   * Asserts the per-state thresholds returned by renderedInteractionScript().
   */
  private function assertStates(string $caseLabel, string $name, array $measured): void {
    $this->assertContains(
      'underline',
      explode(' ', $measured['link']['underline']),
      sprintf('%s: accent "%s" rest link must be underlined so G183 is not required.', $caseLabel, $name)
    );
    foreach ($measured['link'] as $state => $m) {
      if ($state === 'underline') {
        continue;
      }
      $this->assertGreaterThanOrEqual(
        4.5,
        $m['ratio'],
        sprintf('%s: accent "%s" link %s text %s on %s must be >=4.5:1 (got %.2f:1).', $caseLabel, $name, $state, $m['fg'], $m['bg'], $m['ratio'])
      );
    }
    foreach ($measured['secondary'] as $state => $m) {
      $this->assertGreaterThanOrEqual(
        4.5,
        $m['ratio'],
        sprintf('%s: accent "%s" secondary button %s text %s on %s must be >=4.5:1 (got %.2f:1).', $caseLabel, $name, $state, $m['fg'], $m['bg'], $m['ratio'])
      );
    }
    foreach ($measured['primary'] as $state => $m) {
      $this->assertGreaterThanOrEqual(
        4.5,
        $m['ratio'],
        sprintf('%s: accent "%s" primary button %s text %s on %s must be >=4.5:1 (got %.2f:1).', $caseLabel, $name, $state, $m['fg'], $m['bg'], $m['ratio'])
      );
    }
    foreach ($measured['summary'] as $state => $m) {
      $this->assertGreaterThanOrEqual(
        4.5,
        $m['ratio'],
        sprintf('%s: accent "%s" details summary %s text %s on %s must be >=4.5:1 (got %.2f:1).', $caseLabel, $name, $state, $m['fg'], $m['bg'], $m['ratio'])
      );
    }
    $this->assertGreaterThanOrEqual(
      3.0,
      $measured['marker']['ratio'],
      sprintf('%s: accent "%s" details disclosure marker %s on %s must be >=3:1 (got %.2f:1).', $caseLabel, $name, $measured['marker']['fg'], $measured['marker']['bg'], $measured['marker']['ratio'])
    );
    foreach (['rest', 'hover', 'checked'] as $radioState) {
      $m = $measured['radio'][$radioState];
      $this->assertGreaterThanOrEqual(
        4.5,
        $m['ratio'],
        sprintf('%s: accent "%s" radio label %s text %s on %s must be >=4.5:1 (got %.2f:1).', $caseLabel, $name, $radioState, $m['fg'], $m['bg'], $m['ratio'])
      );
    }
    $this->assertGreaterThanOrEqual(
      3.0,
      $measured['radio']['boundary']['ratio'],
      sprintf('%s: accent "%s" radio control boundary %s on %s must be >=3:1 (got %.2f:1).', $caseLabel, $name, $measured['radio']['boundary']['fg'], $measured['radio']['boundary']['bg'], $measured['radio']['boundary']['ratio'])
    );
    $this->assertGreaterThanOrEqual(
      3.0,
      $measured['radio']['checkedRing']['ratio'],
      sprintf('%s: accent "%s" radio checked ring %s on %s must be >=3:1 (got %.2f:1).', $caseLabel, $name, $measured['radio']['checkedRing']['fg'], $measured['radio']['checkedRing']['bg'], $measured['radio']['checkedRing']['ratio'])
    );
  }

  /**
   * Script that renders control fixtures and measures each state's computed
   * foreground vs painted background.
   */
  protected function renderedInteractionScript(bool $dark): string {
    $darkJs = $dark ? 'true' : 'false';
    return <<<JS
(function () {
  // Replicate the theme's real dark-mode state: the gin--dark-mode class on
  // <html> plus the color-scheme that drives any light-dark() tokens.
  document.documentElement.classList.toggle('gin--dark-mode', $darkJs);
  document.documentElement.style.colorScheme = $darkJs ? 'dark' : 'light';

  function rgba(s) {
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 1;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
      throw new Error('Canvas 2D context unavailable while resolving: ' + s);
    }
    ctx.clearRect(0, 0, 1, 1);
    ctx.fillStyle = s;
    ctx.fillRect(0, 0, 1, 1);
    const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
    return { r, g, b, a: a / 255 };
  }
  function toRgb(c) {
    return 'rgb(' + Math.round(c.r) + ', ' + Math.round(c.g) + ', ' + Math.round(c.b) + ')';
  }
  // Both alphas are on the 0-1 scale from rgba().
  function over(fg, bg) {
    const fa = fg.a, ba = bg.a;
    if (ba === 0) {
      return { r: fg.r, g: fg.g, b: fg.b, a: fa };
    }
    const a = fa + ba * (1 - fa);
    if (a === 0) {
      return { r: 0, g: 0, b: 0, a: 0 };
    }
    return {
      r: (fg.r * fa + bg.r * ba * (1 - fa)) / a,
      g: (fg.g * fa + bg.g * ba * (1 - fa)) / a,
      b: (fg.b * fa + bg.b * ba * (1 - fa)) / a,
      a,
    };
  }
  function paintedBg(el) {
    let acc = { r: 0, g: 0, b: 0, a: 0 };
    let node = el;
    let guard = 0;
    while (node && node !== document.documentElement && guard++ < 40) {
      const bg = getComputedStyle(node).backgroundColor;
      if (bg && bg !== 'transparent' && bg !== 'rgba(0, 0, 0, 0)') {
        const layer = rgba(bg);
        if (layer.a > 0.01) {
          acc = over(layer, acc);
        }
      }
      node = node.parentElement;
    }
    if (acc.a < 0.999) {
      acc = over(acc, { r: 255, g: 255, b: 255, a: 1 });
    }
    return toRgb(acc);
  }
  function lum(s) {
    // Only ever called on an opaque colour produced by over().
    const [r, g, b, a] = (() => {
      const canvas = document.createElement('canvas');
      canvas.width = canvas.height = 1;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = s;
      ctx.fillRect(0, 0, 1, 1);
      return ctx.getImageData(0, 0, 1, 1).data;
    })();
    if (a !== 255) {
      throw new Error('Expected opaque colour for luminance, got: ' + s);
    }
    const f = value => {
      const x = value / 255;
      return x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  }
  function ratio(a, b) { const A = Math.max(a, b), B = Math.min(a, b); return (A + 0.05) / (B + 0.05); }
  // fgColor and bgLayer may be translucent; both are composited over the
  // element's painted background so the browser's stacking is respected.
  function measure(fgColor, bgLayer, el) {
    const base = paintedBg(el);
    const layer = bgLayer ? toRgb(over(rgba(bgLayer), rgba(base))) : base;
    const fg = toRgb(over(rgba(fgColor), rgba(layer)));
    return { fg, bg: layer, ratio: ratio(lum(fg), lum(layer)) };
  }
  function resolveToken(token, property) {
    const t = document.createElement('div');
    t.style.display = 'none';
    document.body.appendChild(t);
    t.style[property] = 'var(' + token + ')';
    const v = getComputedStyle(t)[property];
    t.remove();
    if (!v) {
      throw new Error('Unresolved token: ' + token);
    }
    return v;
  }

  // ---- Fixtures (appended so production theme styles apply) ----
  const host = document.createElement('main');
  host.setAttribute('class', 'content');
  host.style.position = 'absolute';
  host.style.left = '-9999px';
  host.style.top = '0';
  document.body.appendChild(host);

  const link = document.createElement('a');
  link.setAttribute('href', '#');
  link.textContent = 'Rendered link';
  host.appendChild(link);
  // Second, never-focused link carries the rest underline evidence.
  const linkRestFixture = document.createElement('a');
  linkRestFixture.setAttribute('href', '#');
  linkRestFixture.textContent = 'Rest link';
  host.appendChild(linkRestFixture);
  // Establish real focus on the first link (pseudo-state we can trigger).
  link.focus();

  const secondary = document.createElement('button');
  secondary.setAttribute('type', 'button');
  secondary.setAttribute('class', 'button');
  secondary.textContent = 'Secondary action';
  host.appendChild(secondary);

  const primary = document.createElement('button');
  primary.setAttribute('type', 'button');
  primary.setAttribute('class', 'button button--primary');
  primary.textContent = 'Primary action';
  host.appendChild(primary);

  const details = document.createElement('details');
  details.setAttribute('class', 'gin-details');
  const summary = document.createElement('summary');
  summary.setAttribute('class', 'gin-details__summary');
  summary.textContent = 'Details summary';
  const body = document.createElement('div');
  body.setAttribute('class', 'gin-details__wrapper');
  body.textContent = 'Details content';
  details.appendChild(summary);
  details.appendChild(body);
  host.appendChild(details);

  // ---- Radio-style choice (mirrors the Appearance segmented control) ----
  const radioWrap = document.createElement('div');
  radioWrap.setAttribute('class', 'form-radios');
  radioWrap.style.display = 'inline-flex';
  const radioItem = document.createElement('div');
  radioItem.setAttribute('class', 'form-type--radio form-item');
  const radioInput = document.createElement('input');
  radioInput.setAttribute('type', 'radio');
  radioInput.setAttribute('name', 'choice');
  radioInput.setAttribute('class', 'form-boolean form-boolean--type-radio');
  const radioLabel = document.createElement('label');
  radioLabel.setAttribute('class', 'form-item__label');
  radioLabel.textContent = 'Choice';
  radioItem.appendChild(radioInput);
  radioItem.appendChild(radioLabel);
  radioWrap.appendChild(radioItem);
  host.appendChild(radioWrap);

  // ---- Measure ----
  // Foregrounds are read from the production semantic token resolved on a real
  // element (the browser composes light-dark() and custom-property chains); the
  // backgrounds are the painted ancestry so stacked alpha is respected.
  void host.offsetHeight;
  const linkRest = resolveToken('--admin-color-link', 'color');
  const linkHover = resolveToken('--admin-color-link-hover', 'color');
  const linkActive = resolveToken('--admin-color-link-active', 'color');
  // Focus only removes the underline (:focus sets text-decoration: none), it
  // does not change the colour, so the focused state shares the rest token.
  const linkFocus = linkRest;
  const linkRestUnderline = getComputedStyle(linkRestFixture).textDecorationLine;

  const secondaryRest = getComputedStyle(secondary).color || resolveToken('--admin-color-primary', 'color');
  const secondaryHoverFg = resolveToken('--admin-color-text-on-primary', 'color');
  const secondaryHoverBg = resolveToken('--admin-color-primary-hover', 'backgroundColor');
  const secondaryActiveBg = resolveToken('--admin-color-primary-active', 'backgroundColor');

  const primaryBg = resolveToken('--admin-color-primary', 'backgroundColor');
  const primaryHoverBg = resolveToken('--admin-color-primary-hover', 'backgroundColor');
  const primaryActiveBg = resolveToken('--admin-color-primary-active', 'backgroundColor');
  const primaryFg = resolveToken('--admin-color-text-on-primary', 'color');

  const summaryRest = resolveToken('--admin-color-text', 'color');
  const summaryHoverFg = resolveToken('--admin-color-summary-text', 'color');
  const summaryHoverBg = resolveToken('--admin-color-summary-hover-bg', 'backgroundColor');
  const markerRest = resolveToken('--admin-color-text', 'color');

  const radioRestText = resolveToken('--admin-color-text', 'color');
  const radioHoverText = resolveToken('--admin-color-radio-hover', 'color');
  const radioHoverBg = resolveToken('--admin-color-radio-hover-bg', 'backgroundColor');
  const radioCheckedText = resolveToken('--admin-color-text-on-primary', 'color');
  const radioCheckedBg = resolveToken('--admin-color-primary', 'backgroundColor');
  const radioBoundary = resolveToken('--admin-color-border-input', 'color');
  const radioInputBg = resolveToken('--admin-color-bg-input', 'backgroundColor');
  const checkedRing = resolveToken('--admin-color-primary', 'color');
  const surfaceBg = resolveToken('--admin-color-bg-surface', 'backgroundColor');

  const result = {
    link: {
      rest: measure(linkRest, null, link),
      hover: measure(linkHover, null, link),
      active: measure(linkActive, null, link),
      focus: measure(linkFocus, null, link),
      underline: linkRestUnderline,
    },
    secondary: {
      rest: measure(secondaryRest, null, secondary),
      hover: measure(secondaryHoverFg, secondaryHoverBg, secondary),
      active: measure(secondaryHoverFg, secondaryActiveBg, secondary),
    },
    primary: {
      rest: measure(primaryFg, primaryBg, primary),
      hover: measure(primaryFg, primaryHoverBg, primary),
      active: measure(primaryFg, primaryActiveBg, primary),
      focus: measure(primaryFg, primaryBg, primary),
    },
    summary: {
      rest: measure(summaryRest, null, summary),
      hover: measure(summaryHoverFg, summaryHoverBg, summary),
      open: measure(summaryHoverFg, null, summary),
      'open-hover': measure(summaryHoverFg, summaryHoverBg, summary),
    },
    marker: measure(markerRest, null, summary),
    radio: {
      rest: measure(radioRestText, null, radioLabel),
      hover: measure(radioHoverText, radioHoverBg, radioLabel),
      checked: measure(radioCheckedText, radioCheckedBg, radioLabel),
      boundary: measure(radioBoundary, radioInputBg, radioInput),
      checkedRing: measure(checkedRing, surfaceBg, radioInput),
    },
    host: Boolean(host),
  };
  // Restore the real appearance state so later assertions are not skewed.
  document.documentElement.classList.remove('gin--dark-mode');
  document.documentElement.style.colorScheme = '';
  host.remove();
  document.body.appendChild(document.createElement('span'));
  return result;
})()
JS;
  }

  /**
   * Every accent picker swatch must stay visible against its adjacent surface.
   *
   * The swatch preview (--admin-color-accent-preview) is presentation-only:
   * Light resolves the source accent identity, Dark resolves the visible
   * foreground-safe accent (so dark mode no longer collapses the swatch into
   * the dark surface). The raw input has no border by default, so the swatch
   * boundary must read >= 3:1 (WCAG 1.4.11 non-text) against the actual
   * surface it sits on in each appearance. Auto is covered by forcing the
   * root colour-scheme to the intended branch; the two forced branches are
   * also compared to Light/Dark so an Auto regression cannot slip through.
   *
   * The preview token references only --accent-base (light) and
   * --admin-color-accent-fg (dark). Increase-contrast mode overrides
   * --admin-color-accent-fg with the identical dark formula and leaves
   * --accent-base untouched, so the preview is invariant under that mode and
   * no separate HC case is needed here.
   */
  public function testAccentSwatchRenderedContrast(): void {
    $presets = Helper::accentColors();
    unset($presets['custom']);
    $cases = [
      'Light' => ['mode' => '0', 'scheme' => 'light'],
      'Dark' => ['mode' => '1', 'scheme' => 'dark'],
      'Auto-light' => ['mode' => 'auto', 'scheme' => 'auto-light'],
      'Auto-dark' => ['mode' => 'auto', 'scheme' => 'auto-dark'],
    ];
    $fillByScheme = [];
    foreach ($cases as $caseLabel => $case) {
      $this->config('default_admin.settings')
        ->set('enable_dark_mode', $case['mode'])
        ->set('preset_accent_color', 'blue')
        ->save();
      $this->drupalGet('/admin/appearance/settings/default_admin');
      $this->assertSession()->statusCodeEquals(200);
      $ratios = $this->getSession()->evaluateScript($this->accentSwatchScript($case['scheme']));
      foreach ($presets as $name => $info) {
        $this->assertArrayHasKey($name, $ratios, sprintf('%s: swatch "%s" found.', $caseLabel, $name));
        $this->assertGreaterThanOrEqual(
          3.0,
          $ratios[$name]['ratio'],
          sprintf('%s: swatch "%s" preview %s vs adjacent %s must be >=3:1 (got %.2f:1).', $caseLabel, $name, $ratios[$name]['fill'], $ratios[$name]['adjacent'], $ratios[$name]['ratio'])
        );
        $fillByScheme[$case['scheme']][$name] = $ratios[$name]['fill'];
      }
    }
    foreach ($presets as $name => $info) {
      $this->assertEquals(
        $fillByScheme['light'][$name],
        $fillByScheme['auto-light'][$name],
        sprintf('Auto-light swatch "%s" must equal the explicit Light preview (got %s vs %s).', $name, $fillByScheme['light'][$name], $fillByScheme['auto-light'][$name])
      );
      $this->assertEquals(
        $fillByScheme['dark'][$name],
        $fillByScheme['auto-dark'][$name],
        sprintf('Auto-dark swatch "%s" must equal the explicit Dark preview (got %s vs %s).', $name, $fillByScheme['dark'][$name], $fillByScheme['auto-dark'][$name])
      );
    }
  }

  /**
   * Script resolving every preset swatch preview against its adjacent surface.
   *
   * Forces the root colour-scheme so light-dark() resolves to the requested
   * branch independently of the operating system (needed for Auto), reads each
   * non-custom swatch's computed background plus the nearest opaque ancestor's
   * background, and returns WCAG ratios of the two.
   */
  protected function accentSwatchScript(string $scheme): string {
    $target = match ($scheme) {
      'light', 'auto-light' => 'light',
      'dark', 'auto-dark' => 'dark',
    };
    return <<<JS
(function () {
  document.documentElement.style.colorScheme = '$target';
  function lum(s) {
    if (!CSS.supports('color', s)) {
      throw new Error('Unsupported computed color: ' + s);
    }
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 1;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) {
      throw new Error('Canvas 2D context unavailable while resolving: ' + s);
    }
    ctx.fillStyle = s;
    ctx.fillRect(0, 0, 1, 1);
    const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
    if (a !== 255) {
      throw new Error('Expected opaque computed color, got: ' + s);
    }
    const f = value => {
      const x = value / 255;
      return x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  }
  function ratio(a, b) { const A = Math.max(a, b), B = Math.min(a, b); return (A + 0.05) / (B + 0.05); }
  const out = {};
  document.querySelectorAll('input[name="preset_accent_color"]').forEach((inp) => {
    const key = inp.getAttribute('data-gin-accent');
    if (!key || key === 'custom') {
      return;
    }
    const fill = getComputedStyle(inp).backgroundColor;
    let el = inp, adjacent = null;
    while (el && !adjacent) {
      el = el.parentElement;
      if (!el) {
        break;
      }
      const bg = getComputedStyle(el).backgroundColor;
      if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') {
        adjacent = bg;
      }
    }
    if (!adjacent) {
      throw new Error('No adjacent opaque surface found for swatch: ' + key);
    }
    out[key] = {
      fill,
      adjacent,
      ratio: ratio(lum(fill), lum(adjacent)),
    };
  });
  return out;
})()
JS;
  }

  /**
   * Script resolving the focus colour for a preset against the surfaces.
   */
  protected function focusPresetScript(string $preset): string {
    $json = json_encode($preset);
    return <<<'JS'
(function () {
  const preset = $json;
  const temp = document.createElement('div');
  temp.style.display = 'none';
  temp.setAttribute('data-gin-focus', preset);
  document.body.appendChild(temp);
  function resolve(varName, property) {
    temp.style[property] = 'var(' + varName + ')';
    const v = getComputedStyle(temp)[property];
    temp.style[property] = '';
    return v;
  }
  function lum(s) {
    if (!CSS.supports('color', s)) {
      throw new Error('Unsupported computed color: ' + s);
    }
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 1;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) {
      throw new Error('Canvas 2D context unavailable while resolving: ' + s);
    }
    ctx.fillStyle = s;
    ctx.fillRect(0, 0, 1, 1);
    const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
    if (a !== 255) {
      throw new Error('Expected opaque computed color, got: ' + s);
    }
    const f = value => {
      const x = value / 255;
      return x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  }
  function ratio(a, b) { const A = Math.max(a, b), B = Math.min(a, b); return (A + 0.05) / (B + 0.05); }
  const focus = lum(resolve('--admin-color-focus', 'color'));
  const surfaces = {
    bg: '--admin-color-bg',
    surface: '--admin-color-bg-surface',
    nested: '--admin-color-bg-surface-nested',
    raised: '--admin-color-bg-raised',
    input: '--admin-color-bg-input'
  };
  const out = {};
  for (const key in surfaces) {
    out[key] = ratio(focus, lum(resolve(surfaces[key], 'backgroundColor')));
  }
  return out;
})()
JS;
  }

  /**
   * Script resolving the rendered icon-link icon vs its state backgrounds.
   */
  protected function iconStateScript(): string {
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
    if (!CSS.supports('color', s)) {
      throw new Error('Unsupported computed color: ' + s);
    }
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 1;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) {
      throw new Error('Canvas 2D context unavailable while resolving: ' + s);
    }
    ctx.fillStyle = s;
    ctx.fillRect(0, 0, 1, 1);
    const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
    if (a !== 255) {
      throw new Error('Expected opaque computed color, got: ' + s);
    }
    const f = value => {
      const x = value / 255;
      return x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  }
  function ratio(a, b) { const A = Math.max(a, b), B = Math.min(a, b); return (A + 0.05) / (B + 0.05); }
  const icon = lum(resolve('--admin-color-icon', 'color'));
  const restBg = lum(resolve('--icon-link-bg-color', 'backgroundColor'));
  const hoverBg = lum(resolve('--icon-link--hover-bg-color', 'backgroundColor'));
  const activeBg = lum(resolve('--icon-link--active-bg-color', 'backgroundColor'));
  const activeIcon = lum(resolve('--icon-link--active-icon-color', 'color'));
  return {
    rest: ratio(icon, restBg),
    hover: ratio(icon, hoverBg),
    active: ratio(activeIcon, activeBg)
  };
})()
JS;
  }

  /**
   * Resolves and returns a single computed CSS variable's used colour.
   */
  protected function resolveColor(string $var): string {
    $script = '(() => { const t = document.createElement("div"); t.style.display = "none"; document.body.appendChild(t); t.style.backgroundColor = `var(' . $var . ')`; let c = getComputedStyle(t).backgroundColor; c = c || t.style.backgroundColor; t.remove(); return c; })()';
    return (string) $this->getSession()->evaluateScript($script);
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
    if (!CSS.supports('color', s)) {
      throw new Error('Unsupported computed color: ' + s);
    }
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 1;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) {
      throw new Error('Canvas 2D context unavailable while resolving: ' + s);
    }
    ctx.fillStyle = s;
    ctx.fillRect(0, 0, 1, 1);
    const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
    if (a !== 255) {
      throw new Error('Expected opaque computed color, got: ' + s);
    }
    const f = value => {
      const x = value / 255;
      return x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
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
