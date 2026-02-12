import { test, expect, drupalInstallModule } from '../drupal-fixtures';

const testValues = {
  top: 200,
  right: 110,
  bottom: 145,
  left: 310,
};

const testElements = `
  <div
    data-offset-top
    style="
      background-color: red;
      height: 110px;
      left: 0;
      position: fixed;
      right: 0;
      top: 90px;
      width: 100%;"
  ></div>

  <div
    data-offset-right
    style="
      background-color: blue;
      bottom: 0;
      height: 100%;
      position: fixed;
      right: 10px;
      top: 0;
      width: 100px;"
  ></div>

  <div
    data-offset-bottom
    style="
      background-color: yellow;
      bottom: 45px;
      height: 100px;
      left: 0;
      position: fixed;
      right: 0;
      width: 100%;"
  ></div>

  <div
    data-offset-left
    style="
      background-color: orange;
      bottom: 0;
      height: 100%;
      left: 10px;
      position: fixed;
      top: 0;
      width: 300px;"
  ></div>
`;

test.describe('Drupal.displace() API', { tag: ['@core'] }, () => {
  test('Test Drupal.displace() JavaScript API', async ({
    drupalPage: page,
  }) => {
    await drupalInstallModule(page, 'js_displace');
    await page.goto('/');
    await page.locator('body').waitFor({ state: 'visible' });

    // Test displace() returns proper offsets.
    const offsetsCorrect = await page.evaluate(
      ({ values, elements }) => {
        const container = document.createElement('div');
        container.innerHTML = elements;
        document.body.append(container);
        const output = Drupal.displace();
        return (
          output.top === values.top &&
          output.right === values.right &&
          output.bottom === values.bottom &&
          output.left === values.left
        );
      },
      { values: testValues, elements: testElements },
    );
    expect(offsetsCorrect).toBe(true);

    // Test displace() properly sets CSS variables.
    const cssVarsCorrect = await page.evaluate((values) => {
      const rootStyles = getComputedStyle(document.documentElement);
      return (
        rootStyles.getPropertyValue('--drupal-displace-offset-top') ===
          `${values.top}px` &&
        rootStyles.getPropertyValue('--drupal-displace-offset-right') ===
          `${values.right}px` &&
        rootStyles.getPropertyValue('--drupal-displace-offset-bottom') ===
          `${values.bottom}px` &&
        rootStyles.getPropertyValue('--drupal-displace-offset-left') ===
          `${values.left}px`
      );
    }, testValues);
    expect(cssVarsCorrect).toBe(true);
  });
});
