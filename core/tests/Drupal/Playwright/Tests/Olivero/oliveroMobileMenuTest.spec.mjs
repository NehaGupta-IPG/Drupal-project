import {
  test,
  expect,
  drupalInstallModule,
  drupalLoginAsAdmin,
} from '../../drupal-fixtures';

const mobileNavButtonSelector = 'button.mobile-nav-button';
const headerNavSelector = '#header-nav';
const linkSubMenuId = 'primary-menu-item-1';
const buttonSubMenuId = 'primary-menu-item-12';

/**
 * Tab a specified number of times, then check that the focused element
 * matches the given parent selector.
 * @param page
 * @param parentSelector
 * @param tabCount
 * @param tabBackwards
 */
async function focusTrapCheck(page, parentSelector, tabCount, tabBackwards) {
  if (tabBackwards) {
    await page.keyboard.down('Shift');
  }
  for (let i = 0; i < tabCount; i++) {
    await page.keyboard.press('Tab');
    await page.waitForTimeout(50);
  }
  const isFocused = await page.evaluate(
    (sel) => document.activeElement.matches(sel),
    parentSelector,
  );
  expect(isFocused).toBe(true);
  if (tabBackwards) {
    await page.keyboard.up('Shift');
  }
}

test.use({
  drupalInstallOptions: {
    setupFile:
      'core/themes/olivero/tests/TestSite/TestSiteOliveroInstallTestScript.php',
    installProfile: 'minimal',
  },
});

test.describe('Olivero mobile menu', { tag: ['@core', '@olivero'] }, () => {
  test('Verify mobile menu and submenu functionality', async ({
    drupalPage: page,
  }) => {
    await page.setViewportSize({ width: 1000, height: 800 });
    await page.goto('./');
    await expect(page.locator(headerNavSelector)).not.toBeVisible();
    await page.locator(mobileNavButtonSelector).click();
    await page.locator(headerNavSelector).waitFor({ state: 'visible' });

    // Test interactions for normal <a> menu links.
    await expect(page.locator(`#${linkSubMenuId}`)).not.toBeVisible();
    await expect(
      page.locator(`[aria-controls="${linkSubMenuId}"]`),
    ).toHaveAttribute('aria-expanded', 'false');

    await page
      .locator(`[aria-controls="${linkSubMenuId}"]`)
      .waitFor({ state: 'visible' });
    await page.locator(`[aria-controls="${linkSubMenuId}"]`).click();
    await page.locator(`#${linkSubMenuId}`).waitFor({ state: 'visible' });
    await expect(
      page.locator(`[aria-controls="${linkSubMenuId}"]`),
    ).toHaveAttribute('aria-expanded', 'true');

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

  test('Verify mobile menu focus trap', async ({ drupalPage: page }) => {
    await page.setViewportSize({ width: 1000, height: 800 });
    await page.goto('./');
    await page.locator(mobileNavButtonSelector).click();
    await page.locator(headerNavSelector).waitFor({ state: 'visible' });

    await focusTrapCheck(
      page,
      `${headerNavSelector} *, ${mobileNavButtonSelector}`,
      17,
    );
    await focusTrapCheck(
      page,
      `${headerNavSelector} *, ${mobileNavButtonSelector}`,
      19,
      true,
    );
  });

  test('Verify parent button focus on ESC in narrow navigation', async ({
    drupalPage: page,
  }) => {
    await page.setViewportSize({ width: 1000, height: 800 });
    await page.goto('./node');
    await page.locator('body').waitFor({ state: 'visible' });
    await page.locator(mobileNavButtonSelector).click();
    await page.locator(headerNavSelector).waitFor({ state: 'visible' });

    // Test ESC on regular link's button.
    await page
      .locator(`[aria-controls="${linkSubMenuId}"]`)
      .waitFor({ state: 'visible' });
    await page.locator(`[aria-controls="${linkSubMenuId}"]`).click();
    await page.locator(`#${linkSubMenuId}`).waitFor({ state: 'visible' });

    await page.keyboard.press('Tab');
    await page.waitForTimeout(50);
    let isFocused = await page.evaluate(
      (id) => document.activeElement.matches(`#${id} *`),
      linkSubMenuId,
    );
    expect(isFocused).toBe(true);

    await page.keyboard.press('Escape');
    await page.waitForTimeout(50);
    isFocused = await page.evaluate(
      (id) => document.activeElement.matches(`[aria-controls="${id}"]`),
      linkSubMenuId,
    );
    expect(isFocused).toBe(true);

    // Test ESC on route:<button> button.
    await page.locator(`[aria-controls="${buttonSubMenuId}"]`).click();
    await page.locator(`#${buttonSubMenuId}`).waitFor({ state: 'visible' });

    await page.keyboard.press('Tab');
    await page.waitForTimeout(50);
    isFocused = await page.evaluate(
      (id) => document.activeElement.matches(`#${id} *`),
      buttonSubMenuId,
    );
    expect(isFocused).toBe(true);

    await page.keyboard.press('Escape');
    await page.waitForTimeout(50);
    isFocused = await page.evaluate(
      (id) => document.activeElement.matches(`[aria-controls="${id}"]`),
      buttonSubMenuId,
    );
    expect(isFocused).toBe(true);
  });

  test('Verify clicks on hashes close mobile menu', async ({
    drupalPage: page,
  }) => {
    await page.setViewportSize({ width: 1000, height: 800 });
    await page.goto('./node');
    await page.locator('body').waitFor({ state: 'visible' });
    await page.locator(mobileNavButtonSelector).click();
    await page.locator(headerNavSelector).waitFor({ state: 'visible' });
    await page.locator('[href="#footer"]').click();
    await page.locator(headerNavSelector).waitFor({ state: 'hidden' });
  });

  test('Verify mobile menu works with Big Pipe when authenticated', async ({
    drupalPage: page,
  }) => {
    await page.setViewportSize({ width: 1000, height: 800 });
    await drupalInstallModule(page, 'big_pipe');
    await drupalLoginAsAdmin(page);
    await page.goto('./');
    await expect(page.locator(headerNavSelector)).not.toBeVisible();
    await page.locator(mobileNavButtonSelector).click();
    await page.locator(headerNavSelector).waitFor({ state: 'visible' });
  });
});
