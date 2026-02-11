const {
  test,
  expect,
  drupalCreateUser,
  drupalLogin,
} = require('../../drupal-fixtures');

const tableSelector = '#edit-field-multiple-value-form-field-wrapper table';
const tableHeaderSelector = '#edit-field-multiple-value-form-field-wrapper th';
const headerSelector = '#edit-field-multiple-value-form-field-wrapper h4';

test.use({
  drupalInstallOptions: {
    setupFile:
      'core/tests/Drupal/TestSite/TestSiteOliveroInstallTestScript.php',
    installProfile: 'minimal',
  },
});

test.describe(
  'Olivero multi-value form fields',
  { tag: ['@core', '@olivero'] },
  () => {
    test('correct classes added to table and header', async ({
      drupalPage: page,
    }) => {
      await drupalCreateUser(page, {
        name: 'user',
        password: '123',
        permissions: ['access site-wide contact form'],
      });
      await drupalLogin(page, { name: 'user', password: '123' });

      await page.setViewportSize({ width: 1400, height: 800 });
      await page.goto('/contact/olivero_test_contact_form');
      await expect(page.locator(tableSelector)).toBeVisible();
      await expect(page.locator(tableSelector)).toHaveClass(
        /tabledrag-disabled/,
      );
      await expect(page.locator(tableSelector)).toHaveClass(
        /js-tabledrag-disabled/,
      );
      await expect(page.locator(tableHeaderSelector).first()).toHaveClass(
        /is-disabled/,
      );
      await expect(page.locator(headerSelector)).toHaveClass(
        /form-item__label/,
      );
      await expect(page.locator(headerSelector)).toHaveClass(
        /form-item__label--multiple-value-form/,
      );
      await expect(page.locator(headerSelector)).toHaveClass(
        /js-form-required/,
      );
      await expect(page.locator(headerSelector)).toHaveClass(/form-required/);
    });
  },
);
