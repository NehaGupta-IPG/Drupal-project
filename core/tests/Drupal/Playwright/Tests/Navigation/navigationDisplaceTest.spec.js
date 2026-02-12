import {
  test,
  expect,
  drupalInstallModule,
  drupalLoginAsAdmin,
} from '../../drupal-fixtures.js';

test.describe('Navigation displace', { tag: ['@core', '@navigation'] }, () => {
  test('Verify displace attribute', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'navigation', true);
    await drupalInstallModule(page, 'big_pipe');

    await page.setViewportSize({ width: 1220, height: 800 });
    await drupalLoginAsAdmin(page);
    await page.goto('/admin/');
    await expect(
      page.locator('.admin-toolbar__displace-placeholder[data-offset-left]'),
    ).toBeAttached();
  });
});
