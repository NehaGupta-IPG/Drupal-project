import {
  test,
  expect,
  drupalLoginAsAdmin,
  drupalLogout,
} from '../../drupal-fixtures';

const headerNavSelector = '#header-nav';
const linkSubMenuId = 'primary-menu-item-1';
const buttonSubMenuId = 'primary-menu-item-12';

test.use({
  drupalInstallOptions: {
    setupFile:
      'core/tests/Drupal/TestSite/TestSiteOliveroInstallTestScript.php',
    installProfile: 'minimal',
  },
});

test.describe('Olivero desktop menu', { tag: ['@core', '@olivero'] }, () => {
  test.beforeEach(async ({ drupalPage: page }) => {
    // Set max-nesting depth to unlimited so tertiary menu items are visible.
    await drupalLoginAsAdmin(page);
    await page.goto('./admin/structure/block/manage/olivero_main_menu');
    await page
      .locator('[data-drupal-selector="edit-settings-depth"]')
      .waitFor({ state: 'visible' });
    await page
      .locator('[data-drupal-selector="edit-settings-depth"]')
      .selectOption({ label: 'Unlimited' });
    await page.locator('[data-drupal-selector="edit-actions-submit"]').click();
    await page.locator('body').waitFor({ state: 'visible' });
    await drupalLogout(page);
    await page.setViewportSize({ width: 1600, height: 800 });
  });

  test('Verify desktop menu click functionality', async ({
    drupalPage: page,
  }) => {
    await page.goto('./node');
    await page.locator(headerNavSelector).waitFor({ state: 'visible' });
    await expect(page.locator(`#${linkSubMenuId}`)).not.toBeVisible();
    await expect(
      page.locator(`[aria-controls="${linkSubMenuId}"]`),
    ).toHaveAttribute('aria-expanded', 'false');

    await page.locator(`[aria-controls="${linkSubMenuId}"]`).click();
    await expect(page.locator(`#${linkSubMenuId}`)).toBeVisible();
    await expect(
      page.locator(`[aria-controls="${linkSubMenuId}"]`),
    ).toHaveAttribute('aria-expanded', 'true');

    // Verify tertiary menu item exists.
    await expect(
      page.locator('#primary-menu-item-11 .primary-nav__menu-link--level-3'),
    ).toBeVisible();

    // Test interactions for route:<button> menu links.
    await expect(page.locator(`#${buttonSubMenuId}`)).not.toBeVisible();
    await expect(
      page.locator(`[aria-controls="${buttonSubMenuId}"]`),
    ).toHaveAttribute('aria-expanded', 'false');

    await page.locator(`[aria-controls="${buttonSubMenuId}"]`).click();
    await expect(page.locator(`#${buttonSubMenuId}`)).toBeVisible();
    await expect(
      page.locator(`[aria-controls="${buttonSubMenuId}"]`),
    ).toHaveAttribute('aria-expanded', 'true');
  });

  test('Verify desktop menu hover functionality', async ({
    drupalPage: page,
  }) => {
    await page.goto('./node');
    await page.locator(headerNavSelector).waitFor({ state: 'visible' });
    await expect(page.locator(headerNavSelector)).toBeVisible();
    await expect(page.locator(`#${linkSubMenuId}`)).not.toBeVisible();

    await page.locator(`[aria-controls="${linkSubMenuId}"]`).hover();
    await expect(page.locator(`#${linkSubMenuId}`)).toBeVisible();
    await expect(
      page.locator(`[aria-controls="${linkSubMenuId}"]`),
    ).toHaveAttribute('aria-expanded', 'true');

    await expect(page.locator(`#${buttonSubMenuId}`)).not.toBeVisible();
    await page.locator(`[aria-controls="${buttonSubMenuId}"]`).hover();
    await expect(page.locator(`#${buttonSubMenuId}`)).toBeVisible();
    await expect(
      page.locator(`[aria-controls="${buttonSubMenuId}"]`),
    ).toHaveAttribute('aria-expanded', 'true');
  });

  test('Verify desktop menu converts to mobile if it gets too long', async ({
    drupalPage: page,
  }) => {
    await page.goto('./node');
    await page.locator('body').waitFor({ state: 'visible' });
    await expect(page.locator('body.is-always-mobile-nav')).not.toBeAttached();

    await page.setViewportSize({ width: 1220, height: 800 });
    // Make the site branding wider to force mobile mode.
    await page.evaluate(() => {
      document.querySelector('.site-branding__name').style.width = '350px';
    });
    await expect(page.locator('body.is-always-mobile-nav')).toBeAttached();
  });
});
