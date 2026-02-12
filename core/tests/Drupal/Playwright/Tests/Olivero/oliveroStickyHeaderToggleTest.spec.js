import { test, expect } from '../../drupal-fixtures.js';

const buttonSelector = 'button.sticky-header-toggle';
const mainMenuSelector = '#block-olivero-main-menu';

test.use({
  drupalInstallOptions: {
    setupFile:
      'core/tests/Drupal/TestSite/TestSiteOliveroInstallTestScript.php',
    installProfile: 'minimal',
  },
});

test.describe('Olivero sticky header', { tag: ['@core', '@olivero'] }, () => {
  test('On scroll, menu collapses to burger menu', async ({
    drupalPage: page,
  }) => {
    await page.setViewportSize({ width: 1400, height: 800 });
    await page.goto('/node');
    // Button is in the DOM but invisible (opacity: 0) before scrolling.
    await expect(page.locator(buttonSelector)).toHaveCSS('opacity', '0');
    await expect(page.locator(buttonSelector)).toHaveAttribute(
      'aria-checked',
      'false',
    );

    // Scroll down by clicking the Drupal logo in the footer area.
    await page.locator('.block-system-powered-by-block .drupal-logo').click();
    // After scroll, .is-fixed is added and button becomes opacity: 1.
    await expect(page.locator(buttonSelector)).toHaveCSS('opacity', '1');
    // Header inner is hidden via opacity: 0 and transform: translateX(-101%).
    await expect(page.locator('#site-header__inner')).toHaveCSS('opacity', '0');

    await page.locator(buttonSelector).click();
    await expect(page.locator(mainMenuSelector)).toBeVisible();
    await expect(page.locator(buttonSelector)).toHaveAttribute(
      'aria-checked',
      'true',
    );

    // Sticky header should remain open after page reload in open state.
    await page.goto('/node');
    await expect(page.locator(mainMenuSelector)).toBeVisible();
    await expect(page.locator(buttonSelector)).toHaveAttribute(
      'aria-checked',
      'true',
    );
  });
});
