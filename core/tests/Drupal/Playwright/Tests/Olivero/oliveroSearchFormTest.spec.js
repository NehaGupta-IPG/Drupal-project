const {
  test,
  expect,
  drupalCreateUser,
  drupalLogin,
} = require('../../drupal-fixtures');

const mobileNavButtonSelector = 'button.mobile-nav-button';
const headerNavSelector = '#header-nav';
const searchButtonSelector = 'button.block-search-wide__button';
const searchFormSelector = '.search-form.search-block-form';
const searchWideSelector = '.block-search-wide__wrapper';
const searchWideInputSelector = '#edit-keys--2';
const searchNarrowSelector = '.block-search-narrow';

test.use({
  drupalInstallOptions: {
    setupFile:
      'core/tests/Drupal/TestSite/TestSiteOliveroInstallTestScript.php',
    installProfile: 'minimal',
  },
});

test.describe('Olivero search form', { tag: ['@core', '@olivero'] }, () => {
  test.beforeEach(async ({ drupalPage: page }) => {
    await drupalCreateUser(page, {
      name: 'user',
      password: '123',
      permissions: ['search content', 'use advanced search'],
    });
    await drupalLogin(page, { name: 'user', password: '123' });
  });

  test('search wide form is accessible and altered', async ({
    drupalPage: page,
  }) => {
    await page.setViewportSize({ width: 1400, height: 800 });
    await page.goto('/');
    await page.locator(searchButtonSelector).waitFor({ state: 'visible' });
    await expect(page.locator(searchButtonSelector)).toHaveAttribute(
      'aria-expanded',
      'false',
    );

    await page.locator(searchButtonSelector).click();
    await page.locator(searchWideInputSelector).waitFor({ state: 'visible' });
    await expect(page.locator(searchButtonSelector)).toHaveAttribute(
      'aria-expanded',
      'true',
    );
    await expect(page.locator(searchWideInputSelector)).toHaveAttribute(
      'placeholder',
      /Search by keyword or phrase\./,
    );
    await expect(page.locator(searchWideInputSelector)).toHaveAttribute(
      'title',
      /Enter the terms you wish to search for\./,
    );
    await expect(
      page.locator('button.search-form__submit').first(),
    ).toBeAttached();

    // Close search form by clicking body.
    await page.locator('body').click();
    await page.locator(searchWideSelector).waitFor({ state: 'hidden' });
    await expect(page.locator(searchButtonSelector)).toHaveAttribute(
      'aria-expanded',
      'false',
    );
  });

  test('Test focus management', async ({ drupalPage: page }) => {
    await page.setViewportSize({ width: 1400, height: 800 });
    await page.goto('/');
    await page.locator(searchButtonSelector).waitFor({ state: 'visible' });
    await page.locator(searchButtonSelector).click();
    await page.locator(searchWideInputSelector).waitFor({ state: 'visible' });
    // Wait for transitionend event to fire.
    await page.waitForTimeout(400);

    // Focus should be on the wide search input.
    const inputFocused = await page.evaluate(
      (sel) => document.activeElement.matches(sel),
      searchWideInputSelector,
    );
    expect(inputFocused).toBe(true);

    // Shift+Tab should keep search form visible (focus on disclosure button).
    await page.keyboard.down('Shift');
    await page.keyboard.press('Tab');
    await page.waitForTimeout(50);
    await expect(page.locator(searchWideSelector)).toBeVisible();

    // Another Tab (with Shift held) should close search form.
    await page.keyboard.press('Tab');
    await page.keyboard.up('Shift');
    await page.waitForTimeout(50);
    await page.locator(searchWideSelector).waitFor({ state: 'hidden' });
  });

  test('search narrow form is accessible', async ({ drupalPage: page }) => {
    await page.setViewportSize({ width: 1000, height: 800 });
    await page.goto('/');
    await page.locator(mobileNavButtonSelector).click();
    await page.locator(headerNavSelector).waitFor({ state: 'visible' });
    await expect(
      page.locator(`${searchNarrowSelector} ${searchFormSelector}`),
    ).toBeVisible();
  });

  test('submit button styled as primary on forms with <= 2 actions', async ({
    drupalPage: page,
  }) => {
    await page.setViewportSize({ width: 1400, height: 800 });
    await page.goto('/form-test/object-controller-builder');
    await expect(
      page.locator('#edit-actions input[type=submit].button--primary'),
    ).toBeAttached();
  });

  test('search page is altered', async ({ drupalPage: page }) => {
    await page.setViewportSize({ width: 1400, height: 800 });
    await page.goto('/search');
    await expect(
      page.locator('.search-form input[name=keys]').first(),
    ).toHaveAttribute('placeholder', /Search by keyword or phrase\./);
    await expect(
      page.locator('.search-form input[name=keys]').first(),
    ).toHaveAttribute('title', /Enter the terms you wish to search for\./);
    await expect(
      page.locator('#edit-basic input[type=submit].button--primary'),
    ).toBeAttached();
    await expect(
      page.locator('#edit-advanced input[type=submit].button--primary'),
    ).toBeAttached();
  });
});
