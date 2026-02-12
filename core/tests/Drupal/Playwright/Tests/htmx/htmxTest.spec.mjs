import { test, expect } from '../../drupal-fixtures';

const scriptSelector = 'script[src*="test_htmx/js/behavior.js"]';
const cssSelector = 'link[rel="stylesheet"][href*="test_htmx/css/style.css"]';
const elementSelector = '.ajax-content';
const elementInitSelector = `${elementSelector}[data-once="htmx-init"]`;

test.use({
  drupalInstallOptions: {
    setupFile: 'core/tests/Drupal/TestSite/HtmxAssetLoadTestSetup.php',
    installProfile: 'minimal',
  },
});

test.describe('HTMX', { tag: ['@core', '@htmx'] }, () => {
  test('Asset Load', async ({ drupalPage: page }) => {
    // Verify the source markup has the initialized element.
    await page.goto('/htmx-test-attachments/replace');
    await page.locator('body').waitFor({ state: 'visible' });
    await expect(page.locator(elementInitSelector)).toBeAttached();

    // Load the page with htmx-enhanced button.
    await page.goto('/htmx-test-attachments/page');
    await page.locator('body').waitFor({ state: 'visible' });
    await expect(page.locator(scriptSelector)).not.toBeAttached();
    await expect(page.locator(cssSelector)).not.toBeAttached();

    await page.locator('[name="replace"]').waitFor({ state: 'visible' });
    await page.locator('[name="replace"]').click();
    await page.locator(elementSelector).waitFor({ state: 'visible' });
    await page.locator(elementInitSelector).waitFor({ state: 'visible' });
    await expect(page.locator(scriptSelector)).toBeAttached();
    await expect(page.locator(cssSelector)).toBeAttached();
  });

  test('Asset Load with wrapper format', async ({ drupalPage: page }) => {
    await page.goto('/htmx-test-attachments/wrapper');
    await page.locator('body').waitFor({ state: 'visible' });
    await expect(page.locator(scriptSelector)).not.toBeAttached();
    await expect(page.locator(cssSelector)).not.toBeAttached();

    await page.locator('[name="replace"]').waitFor({ state: 'visible' });
    await page.locator('[name="replace"]').click();
    await page.locator(elementSelector).waitFor({ state: 'visible' });
    await page.locator(elementInitSelector).waitFor({ state: 'visible' });
    await expect(page.locator(scriptSelector)).toBeAttached();
    await expect(page.locator(cssSelector)).toBeAttached();
  });

  test('Swap Before', async ({ drupalPage: page }) => {
    // Verify source markup.
    await page.goto('/htmx-test-attachments/replace');
    await page.locator('body').waitFor({ state: 'visible' });
    await expect(page.locator(elementInitSelector)).toBeAttached();

    await page.goto('/htmx-test-attachments/before');
    await page.locator('body').waitFor({ state: 'visible' });
    await expect(page.locator(scriptSelector)).not.toBeAttached();
    await expect(page.locator(cssSelector)).not.toBeAttached();

    await page.locator('[name="replace"]').waitFor({ state: 'visible' });
    await page.locator('[name="replace"]').click();
    await page.locator(elementSelector).waitFor({ state: 'visible' });
    await page.locator(elementInitSelector).waitFor({ state: 'visible' });
    await expect(page.locator(scriptSelector)).toBeAttached();
    await expect(page.locator(cssSelector)).toBeAttached();
  });

  test('Swap After', async ({ drupalPage: page }) => {
    // Verify source markup.
    await page.goto('/htmx-test-attachments/replace');
    await page.locator('body').waitFor({ state: 'visible' });
    await expect(page.locator(elementInitSelector)).toBeAttached();

    await page.goto('/htmx-test-attachments/after');
    await page.locator('body').waitFor({ state: 'visible' });
    await expect(page.locator(scriptSelector)).not.toBeAttached();
    await expect(page.locator(cssSelector)).not.toBeAttached();

    await page.locator('[name="replace"]').waitFor({ state: 'visible' });
    await page.locator('[name="replace"]').click();
    await page.locator(elementSelector).waitFor({ state: 'visible' });
    await page.locator(elementInitSelector).waitFor({ state: 'visible' });
    await expect(page.locator(scriptSelector)).toBeAttached();
    await expect(page.locator(cssSelector)).toBeAttached();
  });

  test('Ajax Load HTMX Element', async ({ drupalPage: page }) => {
    // Verify source markup.
    await page.goto('/htmx-test-attachments/replace');
    await page.locator('body').waitFor({ state: 'visible' });
    await expect(page.locator(scriptSelector)).toBeAttached();

    // Load page with AJAX-powered button that inserts an htmx element.
    await page.goto('/htmx-test-attachments/ajax');
    await page.locator('body').waitFor({ state: 'visible' });
    await expect(page.locator(scriptSelector)).not.toBeAttached();
    await expect(page.locator(cssSelector)).not.toBeAttached();

    await page
      .locator('[data-drupal-selector="edit-ajax-button"]')
      .waitFor({ state: 'visible' });
    await page.waitForTimeout(1000);
    await page.locator('[data-drupal-selector="edit-ajax-button"]').click();
    await page.locator('[name="replace"]').waitFor({ state: 'visible' });
    await page.waitForTimeout(1000);
    await page.locator('[name="replace"]').click();
    await page.locator(elementSelector).waitFor({ state: 'visible' });
    await page.locator(elementInitSelector).waitFor({ state: 'visible' });
    await expect(page.locator(scriptSelector)).toBeAttached();
    await expect(page.locator(cssSelector)).toBeAttached();
  });
});
