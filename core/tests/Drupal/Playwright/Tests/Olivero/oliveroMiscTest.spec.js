import { test, expect } from '../../drupal-fixtures.js';

const preloadFontPaths = [
  'core/themes/olivero/fonts/metropolis/Metropolis-Regular.woff2',
  'core/themes/olivero/fonts/metropolis/Metropolis-SemiBold.woff2',
  'core/themes/olivero/fonts/metropolis/Metropolis-Bold.woff2',
  'core/themes/olivero/fonts/lora/lora-v14-latin-regular.woff2',
];

test.use({
  drupalInstallOptions: {
    setupFile:
      'core/tests/Drupal/TestSite/TestSiteOliveroInstallTestScript.php',
    installProfile: 'minimal',
  },
});

test.describe('Olivero misc', { tag: ['@core', '@olivero'] }, () => {
  test('Verify font loading', async ({ drupalPage: page }) => {
    await page.goto('/');
    await page.locator('body').waitFor({ state: 'visible' });

    // Check that <link rel="preload"> tags properly reference fonts.
    const preloadsExist = await page.evaluate((paths) => {
      const basePath = drupalSettings.path.baseUrl;
      return paths.every((path) =>
        document.head.querySelector(`[href="${basePath + path}"]`),
      );
    }, preloadFontPaths);
    expect(preloadsExist).toBe(true);

    // Check that the CSS @font-face declaration has loaded the fonts.
    const fontsLoaded = await page.evaluate(() => {
      document.fonts.load('16px metropolis');
      document.fonts.load('16px Lora');
      return (
        document.fonts.check('16px metropolis') &&
        document.fonts.check('16px Lora')
      );
    });
    expect(fontsLoaded).toBe(true);
  });
});
