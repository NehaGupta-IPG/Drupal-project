import {
  test,
  expect,
  drupalInstallModule,
  assertDeprecationErrorExists,
} from '../drupal-fixtures.js';

test.describe('JavaScript deprecations', { tag: '@core' }, () => {
  test('Test JavaScript deprecations', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'js_deprecation_test');

    await page.goto('/js_deprecation_test');
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('h1')).toContainText('JsDeprecationTest');
    await assertDeprecationErrorExists(
      page,
      'This function is deprecated for testing purposes.',
    );
    await assertDeprecationErrorExists(
      page,
      'This property is deprecated for testing purposes.',
    );
  });
});
