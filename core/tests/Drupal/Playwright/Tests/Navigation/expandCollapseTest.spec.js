const {
  test,
  expect,
  drupalInstallModule,
  drupalLoginAsAdmin,
} = require('../../drupal-fixtures');

const selectors = {
  expandButton: {
    expanded: '.admin-toolbar__expand-button[aria-expanded=true]',
    collapsed: '.admin-toolbar__expand-button[aria-expanded=false]',
  },
  htmlAttribute: {
    expanded: '[data-admin-toolbar="expanded"]',
    collapsed: '[data-admin-toolbar="collapsed"]',
  },
  clearCacheButton: 'input[data-drupal-selector="edit-clear"]',
};

test.describe(
  'Navigation expand/collapse',
  { tag: ['@core', '@navigation'] },
  () => {
    test('Expand/Collapse', async ({ drupalPage: page }) => {
      await drupalInstallModule(page, 'navigation', true);
      await drupalInstallModule(page, 'big_pipe');

      await page.setViewportSize({ width: 1220, height: 800 });
      await drupalLoginAsAdmin(page);
      await page.goto('/admin/config/development/performance');
      await page.locator(selectors.clearCacheButton).click();
      await expect(
        page.locator('[data-once="admin-toolbar-document-triggers-listener"]'),
      ).toBeAttached();

      // The toolbar starts expanded after a fresh install. Collapse it first
      // to establish a known state, then verify expand/collapse toggles work.
      await expect(
        page.locator(selectors.expandButton.expanded),
      ).toBeAttached();
      await expect(
        page.locator(selectors.htmlAttribute.expanded),
      ).toBeAttached();

      // Click to collapse.
      await page.locator(selectors.expandButton.expanded).click();
      await expect(
        page.locator(selectors.expandButton.collapsed),
      ).toBeAttached();
      await expect(
        page.locator(selectors.htmlAttribute.collapsed),
      ).toBeAttached();

      // Click to expand.
      await page.locator(selectors.expandButton.collapsed).click();
      await expect(
        page.locator(selectors.expandButton.expanded),
      ).toBeAttached();
      await expect(
        page.locator(selectors.htmlAttribute.expanded),
      ).toBeAttached();

      // Click to collapse again.
      await page.locator(selectors.expandButton.expanded).click();
      await expect(
        page.locator(selectors.expandButton.collapsed),
      ).toBeAttached();
      await expect(
        page.locator(selectors.htmlAttribute.collapsed),
      ).toBeAttached();
    });
  },
);
