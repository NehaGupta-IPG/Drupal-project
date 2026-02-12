import {
  test,
  expect,
  drupalCreateUser,
  drupalLogin,
} from '../../drupal-fixtures';

const primaryTabsWrapper = '[data-drupal-nav-tabs]';
const activeTab = '.tabs__tab.is-active';
const inactiveTab = '.tabs__tab:not(.is-active)';
const mobileToggle = `${activeTab} .tabs__trigger`;
const hamburgerIcon = `${mobileToggle} .hamburger-icon`;
const closeIcon = `${mobileToggle} .close-icon`;

test.use({
  drupalInstallOptions: {
    setupFile: 'core/tests/Drupal/TestSite/TestSiteClaroInstallTestScript.php',
    installProfile: 'minimal',
  },
});

test.describe('Claro primary tabs', { tag: ['@core', '@claro'] }, () => {
  test.beforeEach(async ({ drupalPage: page }) => {
    await drupalCreateUser(page, {
      name: 'user',
      password: '123',
      permissions: ['administer nodes'],
    });
    await drupalLogin(page, { name: 'user', password: '123' });
  });

  test('Verify desktop primary tab display', async ({ drupalPage: page }) => {
    await page.setViewportSize({ width: 1600, height: 800 });
    await page.goto('/node/1');
    await expect(page.locator(primaryTabsWrapper)).toBeVisible();
    await expect(page.locator(activeTab)).toBeVisible();
    await expect(page.locator(inactiveTab)).toBeVisible();
    await expect(page.locator(mobileToggle)).not.toBeVisible();
  });

  test('Verify mobile tab display and click functionality', async ({
    drupalPage: page,
  }) => {
    await page.setViewportSize({ width: 699, height: 800 });
    await page.goto('/node/1');
    await expect(page.locator(primaryTabsWrapper)).toBeVisible();
    await expect(page.locator(activeTab)).toBeVisible();
    await expect(page.locator(inactiveTab)).not.toBeVisible();
    await expect(page.locator(mobileToggle)).toBeVisible();
    await expect(page.locator(hamburgerIcon)).toBeVisible();
    await expect(page.locator(closeIcon)).not.toBeVisible();
    await expect(page.locator(mobileToggle)).toHaveAttribute(
      'aria-expanded',
      'false',
    );
    await expect(page.locator(hamburgerIcon)).toHaveAttribute(
      'aria-hidden',
      'false',
    );
    await expect(page.locator(closeIcon)).toHaveAttribute(
      'aria-hidden',
      'true',
    );

    await page.locator(mobileToggle).click();
    await expect(page.locator(inactiveTab)).toBeVisible();
    await expect(page.locator(mobileToggle)).toHaveAttribute(
      'aria-expanded',
      'true',
    );
    await expect(page.locator(hamburgerIcon)).not.toBeVisible();
    await expect(page.locator(closeIcon)).toBeVisible();
    await expect(page.locator(hamburgerIcon)).toHaveAttribute(
      'aria-hidden',
      'true',
    );
    await expect(page.locator(closeIcon)).toHaveAttribute(
      'aria-hidden',
      'false',
    );

    await page.locator(mobileToggle).click();
    await expect(page.locator(inactiveTab)).not.toBeVisible();
    await expect(page.locator(mobileToggle)).toHaveAttribute(
      'aria-expanded',
      'false',
    );
    await expect(page.locator(hamburgerIcon)).toBeVisible();
    await expect(page.locator(closeIcon)).not.toBeVisible();
    await expect(page.locator(hamburgerIcon)).toHaveAttribute(
      'aria-hidden',
      'false',
    );
    await expect(page.locator(closeIcon)).toHaveAttribute(
      'aria-hidden',
      'true',
    );
  });
});
