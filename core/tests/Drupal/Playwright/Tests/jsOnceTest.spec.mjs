import { test, expect, drupalInstallModule } from '../drupal-fixtures';

test.describe('Once API', { tag: ['@core'] }, () => {
  test('Test simple once call', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'js_once_test');

    await page.goto('./js_once_test');
    await page
      .locator('[data-drupal-item]')
      .first()
      .waitFor({ state: 'visible' });

    // First call should return 5 items.
    let result = await page.evaluate(() =>
      once('js_once_test', '[data-drupal-item]'),
    );
    expect(result.length).toBe(5);

    // Follow-up calls return empty array.
    result = await page.evaluate(() =>
      once('js_once_test', '[data-drupal-item]'),
    );
    expect(result.length).toBe(0);

    // Once with different id on subset.
    result = await page.evaluate(() =>
      once(
        'js_once_test_extra',
        '[data-drupal-item="1"],[data-drupal-item="2"]',
      ),
    );
    expect(result.length).toBe(2);

    result = await page.evaluate(() =>
      once(
        'js_once_test_extra',
        '[data-drupal-item="1"],[data-drupal-item="2"]',
      ),
    );
    expect(result.length).toBe(0);

    // Remove should return 5 items.
    result = await page.evaluate(() =>
      once.remove('js_once_test', '[data-drupal-item]'),
    );
    expect(result.length).toBe(5);

    // Follow-up remove returns empty.
    result = await page.evaluate(() =>
      once.remove('js_once_test', '[data-drupal-item]'),
    );
    expect(result.length).toBe(0);

    // Remove extra returns 2.
    result = await page.evaluate(() =>
      once.remove(
        'js_once_test_extra',
        '[data-drupal-item="1"],[data-drupal-item="2"]',
      ),
    );
    expect(result.length).toBe(2);

    // Follow-up remove returns empty.
    result = await page.evaluate(() =>
      once.remove(
        'js_once_test_extra',
        '[data-drupal-item="1"],[data-drupal-item="2"]',
      ),
    );
    expect(result.length).toBe(0);
  });
});
