/**
 * Default Admin contrast matrix — 8 appearance states × 8 chromatic presets.
 *
 * Mechanically verifies WCAG 2.2 AA (4.5:1 text, 3:1 UI) and checks the
 * 7:1 enhanced-contrast target via computed styles, plus axe-core on each page
 * load. This catches the #0029a5 on #2d3755 (1.04:1) and accordion hover
 * regressions that only appear when rendered.
 *
 * Run: cd tests/playwright && npx playwright test default-admin-contrast-matrix --project=chromium
 * Or: yarn test:guidepup (runs all)
 */
import { test, expect } from '@playwright/test';
import { AUTH_STATE_FILE } from '../lib/auth-setup';
import { drush } from '../lib/drush-helpers';
import AxeBuilder from '@axe-core/playwright';

const PRESETS = ['blue','purple','teal','green','pink','red','orange','yellow'] as const;
const MODES = [
  { label: 'Light', enable_dark_mode: '0', hc: 0 },
  { label: 'Dark', enable_dark_mode: '1', hc: 0 },
  { label: 'Auto-light', enable_dark_mode: 'auto', hc: 0, colorScheme: 'light' as const },
  { label: 'Auto-dark', enable_dark_mode: 'auto', hc: 0, colorScheme: 'dark' as const },
  { label: 'HC-Light', enable_dark_mode: '0', hc: 1 },
  { label: 'HC-Dark', enable_dark_mode: '1', hc: 1 },
  { label: 'HC-Auto-light', enable_dark_mode: 'auto', hc: 1, colorScheme: 'light' as const },
  { label: 'HC-Auto-dark', enable_dark_mode: 'auto', hc: 1, colorScheme: 'dark' as const },
] as const;

const ROUTE = '/admin/appearance/settings/default_admin';

test.describe('Default Admin — contrast matrix + axe', () => {
  test.use({ storageState: AUTH_STATE_FILE });

  for (const preset of PRESETS) {
    for (const mode of MODES) {
      test(`${preset} — ${mode.label} — no axe color-contrast violations`, async ({ page }) => {
        // Set theme config via drush before navigation.
        drush(`config:set default_admin.settings preset_accent_color ${preset} -y > /dev/null`);
        drush(`config:set default_admin.settings enable_dark_mode ${mode.enable_dark_mode} -y > /dev/null`);
        drush(`config:set default_admin.settings high_contrast_mode ${mode.hc} -y > /dev/null`);
        drush('cr > /dev/null');

        if ((mode as any).colorScheme) {
          await page.emulateMedia({ colorScheme: (mode as any).colorScheme });
        }

        await page.goto(ROUTE, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('main', { timeout: 10000 });

        // Mechanical computed-style checks (catch 1.04:1 before axe).
        const ratios = await page.evaluate(() => {
          function get(v: string, p: string) {
            const t = document.createElement('div');
            t.style.display = 'none';
            document.body.appendChild(t);
            (t.style as any)[p] = `var(${v})`;
            const c = getComputedStyle(t)[p as any];
            t.remove();
            return c;
          }
          function lum(s: string) {
            if (!CSS.supports('color', s)) {
              throw new Error(`Unsupported computed color: ${s}`);
            }
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 1;
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            if (!ctx) {
              throw new Error(`Canvas 2D context unavailable while resolving: ${s}`);
            }
            ctx.fillStyle = s;
            ctx.fillRect(0, 0, 1, 1);
            const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
            if (a !== 255) {
              throw new Error(`Expected opaque computed color, got: ${s}`);
            }
            const f = (value: number) => {
              const x = value / 255;
              return x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4);
            };
            return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
          }
          function ratio(a: number, b: number) { return (Math.max(a,b)+0.05)/(Math.min(a,b)+0.05); }
          const link = lum(get('--admin-color-link','color'));
          const focus = lum(get('--admin-color-focus','color'));
          const acfg = lum(get('--admin-color-accent-fg','color'));
          const bg = lum(get('--admin-color-bg','backgroundColor'));
          const surf = lum(get('--admin-color-bg-surface','backgroundColor'));
          return {
            linkOnBg: ratio(link, bg),
            linkOnSurf: ratio(link, surf),
            focusOnBg: ratio(focus, bg),
            acfgOnBg: ratio(acfg, bg),
          };
        });

        expect(ratios.linkOnBg, `${preset} ${mode.label}: link on bg`).toBeGreaterThanOrEqual(4.5);
        expect(ratios.linkOnSurf, `${preset} ${mode.label}: link on surface`).toBeGreaterThanOrEqual(4.5);
        expect(ratios.focusOnBg, `${preset} ${mode.label}: focus on bg`).toBeGreaterThanOrEqual(3.0);
        expect(ratios.acfgOnBg, `${preset} ${mode.label}: accent-fg on bg`).toBeGreaterThanOrEqual(4.5);
        if (mode.hc) {
          // HC uses the 7:1 enhanced text-contrast target on main surfaces.
          expect(ratios.acfgOnBg, `${preset} ${mode.label} HC: accent-fg on bg 7:1 target`).toBeGreaterThanOrEqual(7.0);
        }

        // Axe color-contrast audit on the fully rendered page.
        const results = await new AxeBuilder({ page })
          .withTags(['wcag2a','wcag2aa'])
          .analyze();

        const contrastViolations = results.violations.filter(v => v.id === 'color-contrast');
        expect(contrastViolations, `${preset} ${mode.label}: axe color-contrast violations: ${JSON.stringify(contrastViolations.map(v=>v.nodes.map(n=>n.html).join('|')), null, 2)}`).toEqual([]);
      });
    }
  }
});
