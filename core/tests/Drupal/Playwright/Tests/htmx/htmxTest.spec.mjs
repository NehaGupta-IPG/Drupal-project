// The javascript that creates dropbuttons is not present on the /page at
// initial load.  If the once data property is added then the JS was loaded
// and triggered on the inserted content.
// @see \Drupal\test_htmx\Controller\HtmxTestAttachmentsController
// @see core/modules/system/tests/modules/test_htmx/js/reveal-merged-settings.js

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
    // Load the route htmx will use for the request on click and confirm the
    // markup we will be looking for is present in the source markup.
    await page.goto('./htmx-test-attachments/replace');
    await page.locator('body').waitFor({ state: 'visible' });
    await expect(page.locator(elementInitSelector)).toBeAttached();

    // Now load the page with the htmx enhanced button and verify the absence
    // of the markup to be inserted. Click the button
    // and check for inserted javascript and markup.
    // Also verifies a target without a swap strategy.
    await page.goto('./htmx-test-attachments/page');
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
    // Now load the page with the htmx enhanced button configured
    // to add the wrapper query parameter and verify the absence
    // of the markup to be inserted. Click the button
    // and check for inserted javascript and markup.
    await page.goto('./htmx-test-attachments/wrapper');
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

  // Verify each swap method.
  test('innerHTML', async ({ drupalPage: page }) => {
    // Load the page with the htmx enhanced button and verify the absence
    // of the markup to be inserted. Click the button
    // and check for inserted javascript and markup.
    await page.goto('./htmx-test-attachments/inner-html/swap');
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

  test('outerHTML', async ({ drupalPage: page }) => {
    await page.goto('./htmx-test-attachments/outer-html/swap');
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

  test('textContent', async ({ drupalPage: page }) => {
    await page.goto('./htmx-test-attachments/text-content/swap');
    await page.locator('body').waitFor({ state: 'visible' });
    await expect(page.locator(scriptSelector)).not.toBeAttached();
    await expect(page.locator(cssSelector)).not.toBeAttached();

    await page.locator('[name="replace"]').waitFor({ state: 'visible' });
    await page.locator('[name="replace"]').click();
    await expect(page.locator('.htmx-test-container')).toHaveText(
      'Initial Content',
    );
    await expect(page.locator(elementSelector)).not.toBeAttached();
  });

  test('beforebegin', async ({ drupalPage: page }) => {
    await page.goto('./htmx-test-attachments/beforebegin/swap');
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

  test('afterbegin', async ({ drupalPage: page }) => {
    await page.goto('./htmx-test-attachments/afterbegin/swap');
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

  test('beforeend', async ({ drupalPage: page }) => {
    await page.goto('./htmx-test-attachments/beforeend/swap');
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

  test('afterend', async ({ drupalPage: page }) => {
    await page.goto('./htmx-test-attachments/afterend/swap');
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

  test('innerMorph', async ({ drupalPage: page }) => {
    await page.goto('./htmx-test-attachments/inner-morph/swap');
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

  test('outerMorph', async ({ drupalPage: page }) => {
    await page.goto('./htmx-test-attachments/outer-morph/swap');
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

  test('outerSync', async ({ drupalPage: page }) => {
    await page.goto('./htmx-test-attachments/outer-sync/swap');
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

  test('delete', async ({ drupalPage: page }) => {
    await page.goto('./htmx-test-attachments/delete');
    await page.locator('body').waitFor({ state: 'visible' });
    await page.locator(elementSelector).waitFor({ state: 'visible' });
    await page.locator(elementInitSelector).waitFor({ state: 'visible' });

    await page.locator('[name="delete"]').waitFor({ state: 'visible' });
    await page.locator('[name="delete"]').click();
    await expect(page.locator(elementSelector)).not.toBeAttached();
    await expect(page.locator(elementInitSelector)).not.toBeAttached();
    await expect(page.locator('body')).toHaveAttribute(
      'data-htmx-behavior-test',
      'detached',
    );
  });

  test('Ajax Load HTMX Element', async ({ drupalPage: page }) => {
    // Load the route htmx will use for the request on click and confirm the
    // markup we will be looking for is present in the source markup.
    await page.goto('./htmx-test-attachments/replace');
    await page.locator('body').waitFor({ state: 'visible' });
    await expect(page.locator(scriptSelector)).toBeAttached();

    // Now load the page with the ajax powered button. Click the button
    // to insert an htmx enhanced button and verify the absence
    // of the markup to be inserted. Click the button
    // and check for inserted javascript and markup.
    await page.goto('./htmx-test-attachments/ajax');
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

  test('Boosted a tag', async ({ drupalPage: page }) => {
    // Load the route htmx will use for the request on click and confirm the
    // markup we will be looking for is present in the source markup.
    await page.goto('./htmx-test-attachments/replace');
    await page.locator('body').waitFor({ state: 'visible' });
    await expect(page.locator(elementInitSelector)).toBeAttached();

    // Now load the page with the htmx enhanced link and verify the absence
    // of the markup to be inserted. Click the link
    // and check for inserted javascript and markup.
    await page.goto('./htmx-test-attachments/body');
    await page.locator('body').waitFor({ state: 'visible' });
    await expect(page.locator(scriptSelector)).not.toBeAttached();
    await expect(page.locator(cssSelector)).not.toBeAttached();

    await page.locator('a.htmx-test-link').waitFor({ state: 'visible' });
    await page.locator('a.htmx-test-link').click();
    await page.locator(elementSelector).waitFor({ state: 'visible' });
    await page.locator(elementInitSelector).waitFor({ state: 'visible' });
    await expect(page.locator(scriptSelector)).toBeAttached();
    await expect(page.locator(cssSelector)).toBeAttached();
  });
});
