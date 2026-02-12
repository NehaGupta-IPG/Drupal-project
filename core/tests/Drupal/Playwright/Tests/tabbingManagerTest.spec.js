import { test, expect, drupalInstallModule } from '../drupal-fixtures.js';

test.describe('Tabbing manager', { tag: ['@core'] }, () => {
  test('test tabbingmanager', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'tabbingmanager_test');
    await page.goto('/tabbingmanager-test');
    await page
      .locator('#tabbingmanager-test-container')
      .waitFor({ state: 'attached' });

    // Tab through the form without tabbing constrained.
    // Tabbing out of the third input should focus the fourth.
    await page.evaluate(() => {
      document.querySelector('#first').focus();
    });
    let focused = await page.evaluate(() => document.activeElement.id);
    expect(focused).toBe('first');

    await page.locator('#first').press('Tab');
    focused = await page.evaluate(() => document.activeElement.id);
    expect(focused).toBe('second');

    await page.locator('#second').press('Tab');
    focused = await page.evaluate(() => document.activeElement.id);
    expect(focused).toBe('third');

    await page.locator('#third').press('Tab');
    focused = await page.evaluate(() => document.activeElement.id);
    expect(focused).toBe('fourth');

    // Tab through with tabbing constrained to the container.
    // Tabbing out of the third (final) input should wrap to the first.
    await page.evaluate(() => {
      Drupal.tabbingManager.constrain(
        document.querySelector('#tabbingmanager-test-container'),
        { trapFocus: true },
      );
      document.querySelector('#first').focus();
    });
    focused = await page.evaluate(() => document.activeElement.id);
    expect(focused).toBe('first');

    await page.locator('#first').press('Tab');
    focused = await page.evaluate(() => document.activeElement.id);
    expect(focused).toBe('second');

    await page.locator('#second').press('Tab');
    focused = await page.evaluate(() => document.activeElement.id);
    expect(focused).toBe('third');

    await page.locator('#third').press('Tab');
    focused = await page.evaluate(() => document.activeElement.id);
    expect(focused).toBe('first');

    // Confirm Shift+Tab on the first element wraps to the third (final).
    await page.evaluate(() => {
      document.querySelector('#first').focus();
    });
    focused = await page.evaluate(() => document.activeElement.id);
    expect(focused).toBe('first');

    await page.locator('#first').press('Shift+Tab');
    focused = await page.evaluate(() => document.activeElement.id);
    expect(focused).toBe('third');
  });
});
