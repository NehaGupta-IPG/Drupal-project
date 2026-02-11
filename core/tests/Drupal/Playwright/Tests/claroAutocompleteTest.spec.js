// cspell:ignore is-autocompleting

const {
  test,
  expect,
  drupalInstallModule,
  drupalEnableTheme,
  drupalCreateUser,
  drupalLogin,
} = require('../drupal-fixtures');

test.describe('Claro autocomplete', { tag: ['@core'] }, () => {
  test('Test Claro autocomplete', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'form_test', true);
    await drupalEnableTheme(page, 'Claro');

    await drupalCreateUser(page, {
      name: 'user',
      password: '123',
      permissions: ['access autocomplete test'],
    });
    await drupalLogin(page, { name: 'user', password: '123' });

    await page.goto('/form-test/autocomplete');
    await page.locator('body').waitFor({ state: 'visible' });

    // Entering a denied first character should not start autocomplete.
    await page.locator('[name="autocomplete_4"]').fill('/');
    await page.waitForTimeout(1000);
    await expect(page.locator('.is-autocompleting')).not.toBeAttached();

    // Autocomplete message and dropdown should not be visible initially.
    await expect(
      page.locator(
        '.js-form-item-autocomplete-3 [data-drupal-selector="autocomplete-message"]',
      ),
    ).not.toBeVisible();
    await expect(page.locator('#ui-id-3.ui-autocomplete')).not.toBeVisible();

    // Typing text should show autocomplete message, then dropdown.
    await page.locator('[name="autocomplete_3"]').pressSequentially('123');
    await page
      .locator(
        '.js-form-item-autocomplete-3 [data-drupal-selector="autocomplete-message"]',
      )
      .waitFor({ state: 'visible' });
    await page
      .locator('#ui-id-3.ui-autocomplete')
      .waitFor({ state: 'visible' });

    // Once dropdown is visible, autocomplete message should be hidden again.
    await expect(
      page.locator(
        '.js-form-item-autocomplete-3 [data-drupal-selector="autocomplete-message"]',
      ),
    ).not.toBeVisible();
  });
});
