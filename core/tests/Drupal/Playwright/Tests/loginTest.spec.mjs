import {
  test,
  expect,
  drupalCreateUser,
  drupalLogin,
  assertNoDeprecationErrors,
} from '../drupal-fixtures';

test.describe('Login', { tag: '@core' }, () => {
  test('Test login', async ({ drupalPage: page }) => {
    // Create a user with permissions.
    await drupalCreateUser(page, {
      name: 'user',
      password: '123',
      permissions: ['access site reports', 'administer site configuration'],
    });

    // Log in as that user.
    await drupalLogin(page, { name: 'user', password: '123' });

    // Navigate to /admin/reports and assert content.
    await page.goto('./admin/reports');
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('h1')).toContainText('Reports');

    // Assert no JS deprecation errors.
    await assertNoDeprecationErrors(page);
  });
});
