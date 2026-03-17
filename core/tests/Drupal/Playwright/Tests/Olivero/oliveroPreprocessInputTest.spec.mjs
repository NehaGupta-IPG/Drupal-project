import { test, expect } from '../../drupal-fixtures';

const checkboxSelector = '#edit-form-checkboxes-title-attribute';

const inputTypes = [
  {
    selector: '#edit-form-textfield-test-title-and-required',
    type: 'text',
    api: 'textfield',
  },
  { selector: '#edit-form-email-title-no-xss', type: 'email', api: 'email' },
  { selector: '#edit-form-tel-title-no-xss', type: 'tel', api: 'tel' },
  { selector: '#edit-form-number-title-no-xss', type: 'number', api: 'number' },
  { selector: '#edit-form-search-title-no-xss', type: 'search', api: 'search' },
  {
    selector: '#edit-form-password-title-no-xss',
    type: 'password',
    api: 'password',
  },
  { selector: '#edit-form-date-title-no-xss', type: 'date', api: 'date' },
  {
    selector: '#edit-form-datetime-title-no-xss-time',
    type: 'time',
    api: 'date',
  },
  { selector: '#edit-form-file-title-no-xss', type: 'file', api: 'file' },
  { selector: '#edit-form-color-title-no-xss', type: 'color', api: 'color' },
  { selector: '#edit-form-url-title-no-xss', type: 'url', api: 'url' },
];

const booleanInputTypes = [
  { selector: '#edit-form-checkboxes-test-first-checkbox', type: 'checkbox' },
  { selector: '#edit-form-radios-title-attribute-first-radio', type: 'radio' },
];

test.use({
  drupalInstallOptions: {
    setupFile:
      'core/tests/Drupal/TestSite/TestSiteOliveroInstallTestScript.php',
    installProfile: 'minimal',
  },
});

test.describe(
  'Olivero preprocess input',
  { tag: ['@core', '@olivero'] },
  () => {
    test('Confirm that title attribute exists if set to display', async ({
      drupalPage: page,
    }) => {
      await page.setViewportSize({ width: 1400, height: 800 });
      await page.goto('./form_test/form-labels');
      await page.locator(checkboxSelector).waitFor({ state: 'visible' });
      await expect(page.locator(checkboxSelector)).toHaveAttribute(
        'title',
        'Checkboxes test (Required)',
      );
    });

    test('Check form element classes by type', async ({ drupalPage: page }) => {
      await page.goto('./form_test/form-labels');

      for (const inputType of inputTypes) {
        await expect(page.locator(inputType.selector)).toHaveClass(
          /form-element/,
        );
        await expect(page.locator(inputType.selector)).toHaveClass(
          new RegExp(`form-element--type-${inputType.type}`),
        );
        await expect(page.locator(inputType.selector)).toHaveClass(
          new RegExp(`form-element--api-${inputType.api}`),
        );
      }

      for (const boolType of booleanInputTypes) {
        await expect(page.locator(boolType.selector)).toHaveClass(
          /form-boolean/,
        );
        await expect(page.locator(boolType.selector)).toHaveClass(
          new RegExp(`form-boolean--type-${boolType.type}`),
        );
      }
    });
  },
);
