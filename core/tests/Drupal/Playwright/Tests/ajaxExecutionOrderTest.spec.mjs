import { test, expect, drupalInstallModule } from '../drupal-fixtures';

test.describe('AJAX execution order', { tag: ['@core', '@ajax'] }, () => {
  test('Test Execution Order', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'ajax_test', true);

    await page.goto('./ajax-test/promise-form');
    await expect(page.locator('body')).toBeVisible();
    await page
      .locator('[data-drupal-selector="edit-test-execution-order-button"]')
      .click();
    await expect(page.locator('#ajax_test_form_promise_wrapper')).toContainText(
      '12345',
    );
  });
});
