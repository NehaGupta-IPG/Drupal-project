import {
  test,
  expect,
  drupalInstallModule,
  assertNoDeprecationErrors,
} from '../drupal-fixtures';

test.describe('States API', { tag: '@core' }, () => {
  test('Test form with state API', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'form_test', true);

    await page.goto('./form-test/javascript-states-form');
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('input[name="textfield"]')).not.toBeVisible();
    await assertNoDeprecationErrors(page);
  });

  test('Test number trigger with spinner widget', async ({
    drupalPage: page,
  }) => {
    await drupalInstallModule(page, 'form_test', true);

    await page.goto('./form-test/javascript-states-form');
    await expect(page.locator('body')).toBeVisible();
    await expect(
      page.locator('#edit-item-visible-when-number-trigger-filled-by-spinner'),
    ).not.toBeVisible();

    // Emulate usage of the spinner browser widget on number inputs.
    await page.evaluate(() => {
      const numberTrigger = document.getElementById('edit-number-trigger');
      numberTrigger.value = 1;
      numberTrigger.dispatchEvent(new Event('change'));
    });

    await expect(
      page.locator('#edit-item-visible-when-number-trigger-filled-by-spinner'),
    ).toBeVisible();
  });
});
