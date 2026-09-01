<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Functional;

use Drupal\default_admin\Helper;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies the Default Admin accent colours meet WCAG 2.2 AA contrast.
 *
 * Light mode is checked against the presets in Helper::accentColors() (the
 * source of truth for every accent) on both #ffffff and #fefeff. Dark mode
 * is guarded by asserting the fixed token definitions exist in the compiled
 * variables stylesheet; the actual rendered dark-mode contrast is covered by
 * AccentColorContrastJavascriptTest.
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class AccentColorContrastTest extends BrowserTestBase {

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
   * Every light-mode accent preset must reach 4.5:1 on white surfaces.
   *
   * Tests against both #ffffff (primary) and #fefeff (off-white used by some
   * content regions). WCAG contrast is symmetric, so this covers both
   * "accent text/links on white" and "white label on an accent button".
   */
  public function testLightModeAccentContrast(): void {
    $colors = Helper::accentColors();
    $this->assertNotEmpty($colors, 'Accent colour presets are defined.');
    $backgrounds = [
      '#ffffff' => 'white',
      '#fefeff' => 'off-white',
    ];

    foreach ($colors as $name => $info) {
      // "custom" is user-provided; the stored hex is only a fallback placeholder.
      if ($name === 'custom') {
        continue;
      }
      $hex = $info['hex'];
      foreach ($backgrounds as $bg_hex => $bg_label) {
        $ratio = $this->contrastRatio($hex, $bg_hex);
        $this->assertGreaterThanOrEqual(
          4.5,
          $ratio,
          sprintf('Accent "%s" (%s) must reach >= 4.5:1 on %s (got %.2f:1).', $name, $hex, $bg_label, $ratio)
        );
      }
    }
  }

  /**
   * Regression guard: the accent tokens stay separate and use one colour model.
   *
   * Accent fills (--admin-color-accent-base) and accent foregrounds
   * (--admin-color-accent-fg) must remain distinct tokens. Both new
   * derivations must use the same colour model. The foreground formula is not
   * a test contract: resulting contrast is asserted in
   * ::testDarkModeForegroundContrast() instead of pinning the literal
   * lightness/chroma constants.
   */
  public function testDarkModeAccentTokens(): void {
    $path = \Drupal::service('extension.list.theme')->getPath('default_admin') . '/css/_variables/variables-color-primitive.css';
    $css = file_get_contents($path);

    $this->assertStringContainsString('--admin-color-accent-fg', $css, 'A separate foreground accent token must exist for dark mode.');
    $this->assertStringContainsString('--admin-color-accent-base', $css, 'A separate fill accent token must exist.');
    $this->assertMatchesRegularExpression(
      '/--admin-color-accent-fg:[^;]*oklch\(from var\(--accent-base\)/',
      $css,
      'The foreground accent derivation must use oklch().'
    );
    $this->assertMatchesRegularExpression(
      '/--admin-color-accent-base:[^;]*oklch\(from var\(--accent-base\)/',
      $css,
      'The fill accent derivation must use the same colour model (oklch()) as the foreground.'
    );
    $this->assertStringNotContainsString(
      'lch(from var(--accent-base) calc(l * 20)',
      $css,
      'The old l*20 formula (which collapsed the accent to near-white) must be gone.'
    );
  }

  /**
   * The derived accent foreground must reach WCAG AA on dark surfaces.
   *
   * The lightness/chroma constants that govern the rendered dark foreground are
   * read from the compiled variables stylesheet, not restated here. Red honours
   * its own shipped dark-fg override (full lightness). Contrast only is asserted.
   */
  public function testDarkModeForegroundContrast(): void {
    $primitive = file_get_contents(\Drupal::service('extension.list.theme')->getPath('default_admin') . '/css/_variables/variables-color-primitive.css');
    [$shared_l, $shared_floor, $shared_c] = $this->oklchDarkFg($primitive, FALSE);
    [$red_l, $red_floor, $red_c] = $this->oklchDarkFg($primitive, TRUE);

    $colors = Helper::accentColors();
    foreach ($colors as $name => $info) {
      if ($name === 'custom') {
        continue;
      }
      [$l, $c] = $name === 'red'
        ? [$red_l, $red_c]
        : [$shared_l * $this->oklchL($info['hex']), $shared_c];
      if ($shared_floor !== NULL && $name !== 'red') {
        $l = max($l, $shared_floor);
      }
      $fg = $this->oklchToHex($l, $c * $this->oklchC($info['hex']), $this->oklchH($info['hex']));
      foreach (['#1b1b1d', '#2a2a2d', '#3b3b3f'] as $bg) {
        $ratio = $this->contrastRatio($fg, $bg);
        $this->assertGreaterThanOrEqual(
          4.5,
          $ratio,
          sprintf('Dark-mode accent "%s" foreground %s on %s must be >=4.5:1 (got %.2f:1).', $name, $fg, $bg, $ratio)
        );
      }
    }
  }

  /**
   * Regression guard: text on the dark-mode solid primary must be white.
   *
   * The dark-mode primary fill is computed from the accent at oklch L*0.45,
   * which is a dark tint. `--admin-color-text-on-primary` must stay white in
   * dark mode; using the light-mode dark value (#111) there produced black on
   * dark teal (1.49:1 on the green preset). White reaches 4.5:1 on every
   * preset's dark primary.
   */
  public function testDarkModeTextOnPrimaryContrast(): void {
    $path = \Drupal::service('extension.list.theme')->getPath('default_admin') . '/css/_variables/variables-color-semantic.css';
    $css = file_get_contents($path);

    $this->assertStringNotContainsString(
      '--admin-color-text-on-primary: light-dark(var(--admin-color-white), #111);',
      $css,
      'Dark-mode text-on-primary must not resolve to #111 on the dark primary fill.'
    );
    $this->assertMatchesRegularExpression(
      '/--admin-color-text-on-primary:\s*light-dark\(var\(--admin-color-white\),\s*(var\(--admin-color-white\)|white|#fff)\);/',
      $css,
      'Dark-mode text-on-primary must be white (it sits on a dark primary fill).'
    );

    $colors = Helper::accentColors();
    $primitive = file_get_contents(\Drupal::service('extension.list.theme')->getPath('default_admin') . '/css/_variables/variables-color-primitive.css');
    [$base_l_mult, $base_c_mult] = $this->oklchAccentBase($primitive);
    foreach ($colors as $name => $info) {
      if ($name === 'custom') {
        continue;
      }
      $hex = $info['hex'];
      $darkPrimary = $this->oklchToHex($base_l_mult * $this->oklchL($hex), $base_c_mult * $this->oklchC($hex), $this->oklchH($hex));
      $ratio = $this->contrastRatio('#ffffff', $darkPrimary);
      $this->assertGreaterThanOrEqual(
        4.5,
        $ratio,
        sprintf('White label on dark-mode primary of "%s" (%s) must be >=4.5:1 (got %.2f:1).', $name, $darkPrimary, $ratio)
      );
    }
  }

  /**
   * Regression: accent fill against an adjacent surface is a separate relationship.
   *
   * The matrix must report this relationship honestly. The verifier previously
   * displayed the accent fill on a surface but computed white-on-fill instead,
   * producing a false PASS. #030089 (blue fill) on #1b1b1d is about 1.12:1.
   */
  public function testAccentFillVsSurfaceRatio(): void {
    $ratio = $this->contrastRatio('#030089', '#1b1b1d');
    $this->assertEqualsWithDelta(1.12, $ratio, 0.05, sprintf('Accent fill #030089 on #1b1b1d must be ~1.12:1 (got %.4f:1).', $ratio));
  }

  /**
   * Regression: white text on the accent fill is a separate relationship.
   *
   * #ffffff on #030089 (blue fill) is about 15.38:1 and passes WCAG 4.5:1.
   * Kept as its own test case — it must never be merged with the fill-vs-surface
   * relationship (they measure different foreground/background pairs).
   */
  public function testWhiteOnAccentFillRatio(): void {
    $ratio = $this->contrastRatio('#ffffff', '#030089');
    $this->assertEqualsWithDelta(15.38, $ratio, 0.05, sprintf('White #ffffff on accent fill #030089 must be ~15.38:1 (got %.4f:1).', $ratio));
    $this->assertGreaterThanOrEqual(
      4.5,
      $ratio,
      'White text on the accent fill must meet WCAG 4.5:1.'
    );
  }

  /**
   * Regression guard: high-contrast must preserve surfaces and hierarchy.
   */
  public function testHighContrastTokens(): void {
    $path = \Drupal::service('extension.list.theme')->getPath('default_admin') . '/css/theme/accent.css';
    $css = file_get_contents($path);
    $this->assertStringContainsString('gin--high-contrast-mode', $css, 'High-contrast mode must be defined.');
    $this->assertStringNotContainsString('--admin-color-bg: #fff', $css, 'High-contrast must not force --admin-color-bg to #fff (preserve surfaces).');
    $this->assertStringNotContainsString('--admin-color-bg: #000', $css, 'High-contrast must not force --admin-color-bg to #000.');
    $this->assertStringContainsString('--admin-color-text-loud: light-dark(#000', $css, 'High-contrast must define loud/default/soft text ramp with light-dark.');
    $this->assertStringContainsString('--admin-color-text: light-dark(#151619', $css, 'High-contrast must define text with light-dark.');
    $this->assertStringContainsString('--admin-color-text-soft: light-dark(#303238', $css, 'High-contrast must define soft text.');
    $this->assertStringContainsString('--admin-color-accent-fg: light-dark(oklch', $css, 'High-contrast accent fg must be light-dark with oklch (preserve hue).');
    $this->assertStringContainsString('@media (forced-colors: none)', $css, 'High-contrast must be protected by forced-colors.');
    $this->assertStringNotContainsString('gin--high-contrast-mode.gin--dark-mode', $css, 'High-contrast must not require both classes on same element (use light-dark).');
    $this->assertMatchesRegularExpression(
      '/--admin-color-border-loud:\s*light-dark\(#[0-9a-fA-F]{3,6},\s*#[0-9a-fA-F]{3,6}\)/',
      $css,
      'High-contrast must resolve border-loud through light-dark.',
    );
    $this->assertStringNotContainsString(
      '--admin-color-border-table: light-dark(#000, #fff);',
      $css,
      'High-contrast table separators must stay subtle, not flatten to black/white.',
    );
    $this->assertStringNotContainsString(
      '--admin-color-border: light-dark(#000, #fff);',
      $css,
      'High-contrast default edges must stay distinct from border-loud and border-table.',
    );
  }

  /**
   * Component interaction states resolve through semantic tokens, not in CSS.
   *
   * The details summary and dark-mode-radio hover styles must consume semantic
   * variables. The Light/Dark decision (the light-dark() branches) lives in the
   * semantic variables. This also pins the state selection at token level: the
   * dark branch of each new token must reference the value that used to be
   * inlined in the component.
   */
  public function testComponentSemanticTokens(): void {
    $semantic = file_get_contents(\Drupal::service('extension.list.theme')->getPath('default_admin') . '/css/_variables/variables-color-semantic.css');
    $details = file_get_contents(\Drupal::service('extension.list.theme')->getPath('default_admin') . '/css/components/details.css');
    $settings = file_get_contents(\Drupal::service('extension.list.theme')->getPath('default_admin') . '/css/components/settings.css');

    $this->assertMatchesRegularExpression(
      '/--admin-color-summary-text:\s*light-dark\(var\(--admin-color-primary-hover\),\s*var\(--admin-color-accent-fg\)\);/',
      $semantic,
      'Summary text must resolve light=primary-hover and dark=accent-fg at the semantic level.'
    );
    $this->assertMatchesRegularExpression(
      '/--admin-color-summary-hover-bg:\s*light-dark\(var\(--admin-color-bg-item-hover\),\s*color-mix\(in sRGB, var\(--admin-color-accent-fg\) 12%, var\(--admin-color-bg-surface\)\)\);/',
      $semantic,
      'Summary hover background must keep its light/dark branches in the semantic token.'
    );
    $this->assertMatchesRegularExpression(
      '/--admin-color-radio-hover:\s*light-dark\(var\(--admin-color-primary\),\s*var\(--admin-color-text-on-primary\)\);/',
      $semantic,
      'Dark-mode radio label hover must resolve dark to text-on-primary at the semantic level.'
    );
    $this->assertMatchesRegularExpression(
      '/--admin-color-radio-hover-bg:\s*light-dark\(var\(--admin-color-primary-soft-hover\),\s*var\(--admin-color-primary-hover\)\);/',
      $semantic,
      'Dark-mode radio label hover background must keep both branches in the semantic token.'
    );

    $this->assertStringContainsString('color: var(--admin-color-summary-text)', $details, 'Details summary hover must consume the semantic summary-text token.');
    $this->assertStringContainsString('background-color: var(--admin-color-summary-hover-bg)', $details, 'Details summary hover must consume the semantic summary-hover-bg token.');
    $this->assertStringContainsString('color: var(--admin-color-radio-hover)', $settings, 'Dark-mode radio hover must consume the semantic radio-hover token.');
    $this->assertStringContainsString('background: var(--admin-color-radio-hover-bg)', $settings, 'Dark-mode radio hover must consume the semantic radio-hover-bg token.');

    $this->assertStringNotContainsString(
      'light-dark(var(--admin-color-primary-hover), var(--admin-color-accent-fg))',
      $details,
      'Details must not inline the light-dark() Light/Dark decision for summary text.'
    );
    $this->assertStringNotContainsString(
      'light-dark(var(--admin-color-bg-item-hover)',
      $details,
      'Details must not inline the light-dark() Light/Dark decision for summary hover background.'
    );
    $this->assertStringNotContainsString(
      'light-dark(var(--admin-color-primary), var(--admin-color-text-on-primary))',
      $settings,
      'Settings must not inline the light-dark() Light/Dark decision for the radio hover color.'
    );
    $this->assertStringNotContainsString(
      'light-dark(var(--admin-color-primary-soft-hover), var(--admin-color-primary-hover))',
      $settings,
      'Settings must not inline the light-dark() Light/Dark decision for the radio hover background.'
    );
  }

  /**
   * High-contrast accent must reach the 7:1 target on main surfaces.
   *
   * Light HC darkens the accent (lower chromatic lightness); dark HC raises it
   * to the normal AA foreground. The exact constants are read from the shipped
   * high-contrast accent-fg branch, not restated here.
   */
  public function testHighContrastAccentContrast(): void {
    $accent = file_get_contents(\Drupal::service('extension.list.theme')->getPath('default_admin') . '/css/theme/accent.css');
    [$light_mult, $light_c] = $this->oklchHCLight($accent);
    [$dark_l_mult, $dark_floor, $dark_c] = $this->oklchHCDark($accent);

    $colors = Helper::accentColors();
    foreach ($colors as $name => $info) {
      if ($name === 'custom') {
        continue;
      }
      $hex = $info['hex'];
      $hcLight = $this->oklchToHex($light_mult * $this->oklchL($hex), $light_c * $this->oklchC($hex), $this->oklchH($hex));
      $rLight = $this->contrastRatio($hcLight, '#ffffff');
      $rNormalLight = $this->contrastRatio($hex, '#ffffff');
      $this->assertGreaterThanOrEqual(7.0, $rLight, sprintf('HC Light accent "%s" %s → %s must be >=7:1 on white (got %.2f:1).', $name, $hex, $hcLight, $rLight));
      $this->assertGreaterThan($rNormalLight, $rLight, sprintf('HC Light "%s" must be higher contrast than normal (HC %.2f vs normal %.2f).', $name, $rLight, $rNormalLight));

      $hcDarkL = max($dark_l_mult * $this->oklchL($hex), $dark_floor);
      $hcDark = $this->oklchToHex($hcDarkL, $dark_c * $this->oklchC($hex), $this->oklchH($hex));
      $rDark = $this->contrastRatio($hcDark, '#000000');
      $this->assertGreaterThanOrEqual(7.0, $rDark, sprintf('HC Dark accent "%s" %s → %s must be >=7:1 on black (got %.2f:1).', $name, $hex, $hcDark, $rDark));
    }
  }

  /**
   * Focus-ring presets must reach 3:1 (WCAG 1.4.11 non-text) on every surface.
   *
   * The focus ring communicates keyboard focus, so it must be distinguishable
   * against all the neutral surfaces a focused control can sit on: main,
   * surface, nested, raised, toolbar and control surfaces. Each focus preset is
   * independent of accent — it is optimised for focus visibility, not brand
   * identity. The shipped light/dark hex pairs are read from the compiled
   * stylesheets, resolving the indirection presets (`neutral` →
   * focus-neutral, `accent` → accent-fg) through the same token graph the
   * browser follows.
   */
  public function testFocusColorContrast(): void {
    $theme = \Drupal::service('extension.list.theme')->getPath('default_admin');
    $semantic = file_get_contents($theme . '/css/_variables/variables-color-semantic.css');
    $accent = file_get_contents($theme . '/css/theme/accent.css');
    $primitive = file_get_contents($theme . '/css/_variables/variables-color-primitive.css');

    // Neutral fallback used by the "neutral" preset.
    [$neutral_light, $neutral_dark] = $this->lightDarkPair($semantic, '--admin-color-focus-neutral');
    // Per-preset pairs as shipped in accent.css.
    $pair = fn(string $preset): array => $this->lightDarkPair($accent, '--admin-color-focus', '[data-gin-focus="' . $preset . '"]');

    $presets = [];
    foreach (['blue', 'green', 'amber'] as $p) {
      $presets[$p] = $pair($p);
    }
    $presets['neutral'] = [$neutral_light, $neutral_dark];
    // "accent" (Accent family) resolves through accent-fg, a foreground-safe
    // accent variation. Evaluate for the default blue accent.
    [$shared_l, $shared_floor, $shared_c] = $this->oklchDarkFg($primitive, FALSE);
    $blue = Helper::accentColors()['blue']['hex'];
    $accentL = max($shared_l * $this->oklchL($blue), $shared_floor);
    $presets['accent'] = [$blue, $this->oklchToHex($accentL, $shared_c * $this->oklchC($blue), $this->oklchH($blue))];

    // Every neutral surface a focused control can sit on, per appearance.
    $surfaces = [
      'light' => [
        'main' => '#fefeff',
        'surface' => '#ffffff',
        'nested' => '#edeff5',
        'raised' => '#ffffff',
        'toolbar' => '#e1eafc',
        'input' => '#ffffff',
      ],
      'dark' => [
        'main' => '#1b1b1d',
        'surface' => '#2a2a2d',
        'nested' => '#3b3b3f',
        'raised' => '#47474c',
        'toolbar' => '#2d3755',
        'input' => '#3b3b3f',
      ],
    ];

    foreach ($presets as $name => [$light_fg, $dark_fg]) {
      $pairs = [
        'light' => [$light_fg, $surfaces['light']],
        'dark' => [$dark_fg, $surfaces['dark']],
      ];
      foreach ($pairs as $mode => [$fg, $bgs]) {
        foreach ($bgs as $surface => $bg) {
          $ratio = $this->contrastRatio($fg, $bg);
          $this->assertGreaterThanOrEqual(
            3.0,
            $ratio,
            sprintf('Focus preset "%s" (%s) %s must reach >= 3:1 on %s %s (got %.2f:1).', $name, $mode, $fg, $surface, $bg, $ratio)
          );
        }
      }
    }
  }

  /**
   * Focus on an accent-filled control uses a two-part indicator.
   *
   * A single focus ring sits ring-on-fill on the primary colour and fails on
   * light surfaces (a blue ring on the accent fill is below 3:1).
   * The focus-gap semantic token inserts a surface ring between the fill and the
   * focus ring. This guards that the primary button and checked checkbox consume
   * that two-part indicator, and that it resolves to >= 3:1 against the fill.
   */
  public function testFocusTwoPartIndicatorOnAccentFill(): void {
    $theme = \Drupal::service('extension.list.theme')->getPath('default_admin');
    $semantic = file_get_contents($theme . '/css/_variables/variables-color-semantic.css');
    $button = file_get_contents($theme . '/css/components/button.css');
    $checkbox = file_get_contents($theme . '/css/components/form--checkbox-radio.css');

    $this->assertStringContainsString(
      '--admin-color-focus-gap: var(--admin-color-bg-surface);',
      $semantic,
      'The focus-gap separator ring must be a semantic token backed by the surface gap.',
    );

    // Primary button: remove the failing global outline and draw a gap + ring.
    $this->assertMatchesRegularExpression(
      '/\.button--primary:focus,[^{]*\{[^}]*outline:[^}]*!important[^}]*var\(--admin-color-focus-gap\)[^}]*var\(--admin-color-focus\)[^}]*\}/s',
      $button,
      'Primary button focus must switch to a two-part indicator (outline removed, gap ring + focus ring).',
    );

    // Checked checkbox (accent-filled) uses the same two-part indicator.
    $this->assertMatchesRegularExpression(
      '/&:focus\s*\{[^}]*outline:\s*none[^}]*var\(--admin-color-focus-gap\)[^}]*var\(--admin-color-focus\)[^}]*\}/s',
      $checkbox,
      'Checked checkbox focus must use the two-part indicator.',
    );

    // The two-part overrides must be gated behind forced-colors: none so
    // forced color mode can use its native focus indicator.
    $this->assertMatchesRegularExpression(
      '/@media \(forced-colors: none\)\s*\{\s*\.button--primary:focus,[^{]*\{[^}]*outline:[^}]*!important[^}]*var\(--admin-color-focus-gap\)[^}]*var\(--admin-color-focus\)[^}]*\}\s*\}/s',
      $button,
      'Primary button focus override must be wrapped in @media (forced-colors: none).',
    );
    $this->assertMatchesRegularExpression(
      '/@media \(forced-colors: none\)\s*\{\s*&:focus\s*\{[^}]*outline:\s*none[^}]*var\(--admin-color-focus-gap\)[^}]*var\(--admin-color-focus\)[^}]*\}\s*\}/s',
      $checkbox,
      'Checked checkbox focus override must be wrapped in @media (forced-colors: none).',
    );

    // The indicator must differentiate the focus ring from the accent fill.
    // Light: gap is white (surface), so it separates blue ring from blue fill.
    $primary_light = '#0047ab';
    $gap_light = '#ffffff';
    $ring_light = '#003ecc';
    $gap_vs_fill = $this->contrastRatio($gap_light, $primary_light);
    $this->assertGreaterThanOrEqual(3.0, $gap_vs_fill, sprintf('Focus gap on accent fill must be >= 3:1 (got %.2f:1).', $gap_vs_fill));
    $ring_vs_gap = $this->contrastRatio($ring_light, $gap_light);
    $this->assertGreaterThanOrEqual(3.0, $ring_vs_gap, sprintf('Focus ring on gap must be >= 3:1 (got %.2f:1).', $ring_vs_gap));
  }

  /**
   * Link / focus / hover must be visible on all surfaces in both modes.
   *
   * Regression for #0029a5 on #2d3755 (1.04:1) on Appearance Light/Auto.
   * Light link #003ecc on white must be >=4.5:1. Dark link #99b8ff (blue-200)
   * and focus #99b8ff must be >=4.5:1 / >=3:1 on every dark surface including
   * toolbar #2d3755 and raised #47474c. Hover in dark uses accent-fg and is
   * covered by the accent preset test.
   */
  public function testInteractiveColorContrast(): void {
    $light_bgs = ['#ffffff', '#fefeff'];
    $dark_bgs = ['#1b1b1d', '#2a2a2d', '#3b3b3f', '#47474c', '#2d3755'];
    $link_light = '#003ecc';
    $link_dark = '#99b8ff';
    $focus_dark = '#99b8ff';

    foreach ($light_bgs as $bg) {
      $r = $this->contrastRatio($link_light, $bg);
      $this->assertGreaterThanOrEqual(4.5, $r, sprintf('Link %s on %s must be >=4.5:1 (got %.2f:1).', $link_light, $bg, $r));
    }
    foreach ($dark_bgs as $bg) {
      $r = $this->contrastRatio($link_dark, $bg);
      $this->assertGreaterThanOrEqual(4.5, $r, sprintf('Link %s on %s must be >=4.5:1 (got %.2f:1).', $link_dark, $bg, $r));
      $rf = $this->contrastRatio($focus_dark, $bg);
      $this->assertGreaterThanOrEqual(3.0, $rf, sprintf('Focus %s on %s must be >=3:1 (got %.2f:1).', $focus_dark, $bg, $rf));
    }
    // Document the old bug: #0029a5 on #2d3755 was 1.04:1 (invisible) — fixed by using #99b8ff.
  }

  /**
   * Link hover and active are separate states, both dark foreground-safe.
   *
   * Hover and active are distinct steps in light mode (accent-600 vs
   * accent-700). In dark mode both stay foreground-safe: active modifies the
   * foreground-safe accent toward accent-900 with a color-mix(), instead of
   * replacing it with the darker accent step, which would lose contrast on dark
   * surfaces.
   *
   * The assertion protects the rendered outcome, never the mix percentage.
   * The percentage is read from the actual compiled CSS, so changing the mix
   * amount only passes if every preset's dark active still reaches 4.5:1 on
   * every dark surface.
   */
  public function testLinkStateContrast(): void {
    $theme = \Drupal::service('extension.list.theme')->getPath('default_admin');
    $semantic = file_get_contents($theme . '/css/_variables/variables-color-semantic.css');

    $this->assertStringContainsString(
      '--admin-color-link-hover: light-dark(var(--admin-color-accent-600),',
      $semantic,
      'Link hover must use the accent-600 step in light mode.',
    );
    $this->assertStringContainsString(
      '--admin-color-link-active: light-dark(var(--admin-color-accent-700),',
      $semantic,
      'Link active must use a deeper accent-700 step than hover in light mode.',
    );
    $this->assertMatchesRegularExpression(
      '/--admin-color-link-active: light-dark\(\s*var\(--admin-color-accent-700\),\s*color-mix\(in oklch,\s*var\(--admin-color-accent-fg\),\s*var\(--admin-color-accent-900\)\s+\d+%\)\s*\);/',
      $semantic,
      'Dark-mode link active must be a color-mix() of the foreground-safe accent toward accent-900.',
    );
    $this->assertStringNotContainsString(
      '--admin-color-link-active: light-dark(var(--admin-color-accent-700), var(--admin-color-accent-900));',
      $semantic,
    );

    preg_match('/\bcolor-mix\(in oklch,\s*var\(--admin-color-accent-fg\),\s*var\(--admin-color-accent-900\)\s+(\d+)%\)/', $semantic, $match);
    $this->assertNotEmpty($match, 'The dark link-active color-mix percentage must be readable from the compiled CSS.');
    $mix = (int) $match[1] / 100;

    // Read every constant that shapes the dark link-active value from the
    // compiled CSS (the shipped source of truth), never hard-coded values.
    $primitive = file_get_contents($theme . '/css/_variables/variables-color-primitive.css');
    [$shared_l, $shared_floor, $shared_c] = $this->oklchDarkFg($primitive, FALSE);
    [$red_l, , $red_c] = $this->oklchDarkFg($primitive, TRUE);
    [$base_l_mult, $base_c_mult] = $this->oklchAccentBase($primitive);
    $scheme = $this->accentMixBaseScheme($primitive);
    $accent900 = $this->accent900Mix($primitive);
    $fg_dark_mult = 1 - $accent900;
    $scheme_dark = $scheme['dark'];

    $dark_bgs = ['#1b1b1d', '#2a2a2d', '#3b3b3f', '#47474c', '#2d3755'];
    $colors = Helper::accentColors();
    foreach ($colors as $name => $info) {
      if ($name === 'custom') {
        continue;
      }
      $base = $this->hexToOklch($info['hex']);
      // Dark foreground-safe accent. Red honours its shipped lighter override.
      [$fg_l, $fg_c_mult] = $name === 'red' ? [$red_l, $red_c] : [$shared_l * $base['L'], $shared_c];
      if ($shared_floor !== NULL && $name !== 'red') {
        $fg_l = max($fg_l, $shared_floor);
      }
      $fg = $this->oklchToHex($fg_l, $fg_c_mult * $base['C'], $base['H']);
      // Dark fill accent-900: lch mix of the scheme dark against the dark base.
      $d900 = $this->oklchToHex(
        $accent900 * $this->oklchL($scheme_dark) + $fg_dark_mult * ($base_l_mult * $base['L']),
        $accent900 * $this->oklchC($scheme_dark) + $fg_dark_mult * ($base_c_mult * $base['C']),
        $base['H'],
      );
      // Active = oklch color-mix of fg and 900 at the shipped percentage.
      $fg_o = $this->hexToOklch($fg);
      $d900_o = $this->hexToOklch($d900);
      $dh = $d900_o['H'] - $fg_o['H'];
      $dh = $dh > 180 ? $dh - 360 : ($dh < -180 ? $dh + 360 : $dh);
      $active = $this->oklchToHex(
        $fg_o['L'] * (1 - $mix) + $d900_o['L'] * $mix,
        $fg_o['C'] * (1 - $mix) + $d900_o['C'] * $mix,
        $fg_o['H'] + $dh * $mix,
      );
      foreach ($dark_bgs as $bg) {
        $ratio = $this->contrastRatio($active, $bg);
        $this->assertGreaterThanOrEqual(
          4.5,
          $ratio,
          sprintf('Link active "%s" (%s) on %s must be >=4.5:1 (got %.2f:1).', $name, $active, $bg, $ratio),
        );
      }
    }
  }

  /**
   * Reads the dark accent-fg oklch constants shipped in a variables sheet.
   *
   * The shared branch uses `max(calc(l * N), F) calc(c * M) h`; the red
   * override uses a literal lightness `1 calc(c * M) h`. Returns
   * [L base multiplier, L floor (or NULL), C multiplier]. The L base multiplier
   * is the factor applied to the source lightness; a literal L value is returned
   * as the multiplier with a NULL floor (treated as an absolute, not scaled).
   */
  protected function oklchDarkFg(string $css, bool $red): array {
    if ($red) {
      $pattern = '/--admin-color-accent-fg:\s*light-dark\([^,]+,?\s*oklch\(from var\(--accent-base\)\s+([0-9.]+)\s*calc\(c\s*\*\s*([0-9.]+)\)\s*h\)\)?;/';
    }
    else {
      $pattern = '/--admin-color-accent-fg:\s*light-dark\([^,]+,?\s*oklch\(from var\(--accent-base\)\s+max\(calc\(l\s*\*\s*([0-9.]+)\),\s*([0-9.]+)\)\s*calc\(c\s*\*\s*([0-9.]+)\)\s*h\)\)?;/';
    }
    $this->assertMatchesRegularExpression($pattern, $css, $red ? 'A shipped red dark-fg oklch override must exist.' : 'A shipped shared dark-fg oklch expression must exist.');
    preg_match($pattern, $css, $m);
    if ($red) {
      return [(float) $m[1], NULL, (float) $m[2]];
    }
    return [(float) $m[1], (float) $m[2], (float) $m[3]];
  }

  /**
   * Reads the dark accent-base oklch multipliers: [L multiplier, C multiplier].
   */
  protected function oklchAccentBase(string $css): array {
    $pattern = '/--admin-color-accent-base:\s*light-dark\([^,]+,?\s*oklch\(from var\(--accent-base\)\s*calc\(l\s*\*\s*([0-9.]+)\)\s*calc\(c\s*\*\s*([0-9.]+)\)\s*h\)\)?;/';
    $this->assertMatchesRegularExpression($pattern, $css, 'A shipped dark accent-base oklch expression must exist.');
    preg_match($pattern, $css, $m);
    return [(float) $m[1], (float) $m[2]];
  }

  /**
   * Reads the high-contrast light accent-fg oklch multipliers: [L, C].
   */
  protected function oklchHCLight(string $css): array {
    $pattern = '/--admin-color-accent-fg:\s*light-dark\(oklch\(from var\(--accent-base\)\s*calc\(l\s*\*\s*([0-9.]+)\)\s*c\s*h\),\s*oklch\(/';
    $this->assertMatchesRegularExpression($pattern, $css, 'A shipped high-contrast light accent-fg oklch expression must exist.');
    preg_match($pattern, $css, $m);
    return [(float) $m[1], 1.0];
  }

  /**
   * Reads the high-contrast dark accent-fg oklch constraints: [L, floor, C].
   */
  protected function oklchHCDark(string $css): array {
    $pattern = '/--admin-color-accent-fg:\s*light-dark\([^,]+,\s*oklch\(from var\(--accent-base\)\s*max\(calc\(l\s*\*\s*([0-9.]+)\),\s*([0-9.]+)\)\s*calc\(c\s*\*\s*([0-9.]+)\)\s*h\)\);\s*\}/';
    $this->assertMatchesRegularExpression($pattern, $css, 'A shipped high-contrast dark accent-fg oklch expression must exist.');
    preg_match($pattern, $css, $m);
    return [(float) $m[1], (float) $m[2], (float) $m[3]];
  }

  /**
   * Extracts a light-dark() hex pair for a token, optionally within a selector.
   *
   * Returns [light hex, dark hex]. Only literal #rgb/#rrggbb values are read.
   */
  protected function lightDarkPair(string $css, string $token, ?string $selector = NULL): array {
    $token = ltrim($token, '-');
    $token_pat = preg_quote($token, '/');
    $pattern = $selector === NULL
      ? '/--' . $token_pat . ':\s*light-dark\((#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})),\s*(#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}))\)/'
      : '/' . preg_quote($selector, '/') . '\s*\{[^}]*--' . $token_pat . ':\s*light-dark\((#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})),\s*(#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}))\)/';
    $this->assertMatchesRegularExpression($pattern, $css, sprintf('A light-dark() hex pair for --%s%s must exist.', $token, $selector ? ' in ' . $selector : ''));
    preg_match($pattern, $css, $m);
    return [$m[1], $m[2]];
  }

  /**
   * Reads the light/dark literals shipped for --accent-mix-base-scheme.
   *
   * The scheme ships as keyword pairs (black/white), so keywords are mapped to
   * their hex equivalents.
   */
  protected function accentMixBaseScheme(string $css): array {
    $pattern = '/--accent-mix-base-scheme:\s*light-dark\(((?:#[0-9a-fA-F]{3}|#[0-9a-fA-F]{6}|black|white)),\s*((?:#[0-9a-fA-F]{3}|#[0-9a-fA-F]{6}|black|white))\)/';
    $this->assertMatchesRegularExpression($pattern, $css, 'A shipped --accent-mix-base-scheme light-dark() pair must exist.');
    preg_match($pattern, $css, $m);
    $map = static fn(string $v): string => $v === 'black' ? '#000000' : ($v === 'white' ? '#ffffff' : $v);
    return ['light' => $map($m[1]), 'dark' => $map($m[2])];
  }

  /**
   * Reads the accent-900 step mix percentage (fraction 0..1).
   */
  protected function accent900Mix(string $css): float {
    $pattern = '/--admin-color-accent-900:\s*color-mix\(in lch,\s*var\(--accent-mix-base-scheme\)\s+([0-9.]+)%/';
    $this->assertMatchesRegularExpression($pattern, $css, 'A shipped accent-900 color-mix percentage must exist.');
    preg_match($pattern, $css, $m);
    return (float) $m[1] / 100;
  }

  /**
   * Returns the OKLCH lightness of a hex colour.
   */
  protected function oklchL(string $hex): float {
    return $this->hexToOklch($hex)['L'];
  }

  /**
   * Returns the OKLCH chroma of a hex colour.
   */
  protected function oklchC(string $hex): float {
    return $this->hexToOklch($hex)['C'];
  }

  /**
   * Returns the OKLCH hue (degrees) of a hex colour.
   */
  protected function oklchH(string $hex): float {
    return $this->hexToOklch($hex)['H'];
  }

  /**
   * Computes the WCAG 2.1 relative luminance of a #rrggbb (or #rgb) colour.
   */
  protected function relativeLuminance(string $hex): float {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;
    $channel = static function (float $c): float {
      return $c <= 0.04045 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
  }

  /**
   * Computes the WCAG 2.1 contrast ratio between two #rrggbb colours.
   */
  protected function contrastRatio(string $hex1, string $hex2): float {
    $l1 = $this->relativeLuminance($hex1);
    $l2 = $this->relativeLuminance($hex2);
    $lighter = max($l1, $l2);
    $darker = min($l1, $l2);
    return ($lighter + 0.05) / ($darker + 0.05);
  }

  /**
   * Converts a #rrggbb (or #rgb) colour to an [r,g,b] array.
   */
  protected function hexToRgb(string $hex): array {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
  }

  /**
   * Converts a hex colour to OKLab [L, C, H] values (mirrors CSS oklch()).
   */
  protected function hexToOklch(string $hex): array {
    [$r, $g, $b] = array_map(fn($v) => $v / 255, $this->hexToRgb($hex));
    $r = $r > 0.04045 ? pow(($r + 0.055) / 1.055, 2.4) : $r / 12.92;
    $g = $g > 0.04045 ? pow(($g + 0.055) / 1.055, 2.4) : $g / 12.92;
    $b = $b > 0.04045 ? pow(($b + 0.055) / 1.055, 2.4) : $b / 12.92;
    $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
    $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
    $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;
    $lc = pow($l, 1 / 3);
    $mc = pow($m, 1 / 3);
    $sc = pow($s, 1 / 3);
    $ok_l = 0.2104542553 * $lc + 0.7936177850 * $mc - 0.0040720468 * $sc;
    $ok_a = 1.9779984951 * $lc - 2.4285922050 * $mc + 0.4505937099 * $sc;
    $ok_b = 0.0259040371 * $lc + 0.7827717662 * $mc - 0.8086757660 * $sc;
    $chroma = sqrt($ok_a * $ok_a + $ok_b * $ok_b);
    $hue = rad2deg(atan2($ok_b, $ok_a));
    if ($hue < 0) {
      $hue += 360;
    }
    return ['L' => $ok_l, 'C' => $chroma, 'H' => $hue];
  }

  /**
   * Converts OKLab [L, C, H] values to a hex colour (mirrors CSS oklch()).
   */
  protected function oklchToHex(float $ok_l, float $chroma, float $hue): string {
    $hrad = deg2rad($hue);
    $ok_a = $chroma * cos($hrad);
    $ok_b = $chroma * sin($hrad);
    $lc = $ok_l + 0.3963377774 * $ok_a + 0.2158037573 * $ok_b;
    $mc = $ok_l - 0.1055613458 * $ok_a - 0.0638541728 * $ok_b;
    $sc = $ok_l - 0.0894841775 * $ok_a - 1.2914855480 * $ok_b;
    $l = $lc * $lc * $lc;
    $m = $mc * $mc * $mc;
    $s = $sc * $sc * $sc;
    $r = 4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s;
    $g = -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s;
    $b = -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s;
    $r = $r > 0.0031308 ? 1.055 * pow($r, 1 / 2.4) - 0.055 : 12.92 * $r;
    $g = $g > 0.0031308 ? 1.055 * pow($g, 1 / 2.4) - 0.055 : 12.92 * $g;
    $b = $b > 0.0031308 ? 1.055 * pow($b, 1 / 2.4) - 0.055 : 12.92 * $b;
    return sprintf('#%02x%02x%02x', max(0, min(255, (int) round($r * 255))), max(0, min(255, (int) round($g * 255))), max(0, min(255, (int) round($b * 255))));
  }

}
