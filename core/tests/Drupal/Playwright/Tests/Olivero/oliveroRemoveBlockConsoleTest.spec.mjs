import {
  test,
  expect,
  drupalLoginAsAdmin,
  drupalLogout,
} from '../../drupal-fixtures';

test.use({
  drupalInstallOptions: {
    setupFile:
      'core/tests/Drupal/TestSite/TestSiteOliveroInstallTestScript.php',
    installProfile: 'minimal',
  },
});

test.describe(
  'Olivero remove block console',
  { tag: ['@core', '@olivero'] },
  () => {
    test('Verify no console errors after removing blocks', async ({
      drupalPage: page,
    }) => {
      await drupalLoginAsAdmin(page);
      await page.goto('/admin/structure/block');

      // Disable narrow search form block.
      await page
        .locator(
          '[data-drupal-selector="edit-blocks-olivero-search-form-narrow-operations"] .dropbutton-toggle button',
        )
        .click();
      await page
        .locator('[href*="olivero_search_form_narrow/disable"]')
        .click();

      // Disable main menu block.
      await page
        .locator(
          '[data-drupal-selector="edit-blocks-olivero-main-menu-operations"] .dropbutton-toggle button',
        )
        .click();
      await page.locator('[href*="olivero_main_menu/disable"]').click();

      // Disable wide search form block.
      await page
        .locator(
          '[data-drupal-selector="edit-blocks-olivero-search-form-wide-operations"] .dropbutton-toggle button',
        )
        .click();
      await page.locator('[href*="olivero_search_form_wide/disable"]').click();

      // Disable user account menu block.
      await page
        .locator(
          '[data-drupal-selector="edit-blocks-olivero-account-menu-operations"] .dropbutton-toggle button',
        )
        .click();
      await page.locator('[href*="olivero_account_menu/disable"]').click();

      await drupalLogout(page);

      // Verify no console errors on the front page.
      await page.goto('/');
      await page.locator('body').waitFor({ state: 'visible' });
      const noErrors = await page.evaluate(() => Drupal.errorLog.length === 0);
      expect(noErrors).toBe(true);
    });
  },
);
