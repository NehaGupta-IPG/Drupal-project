import {
  test,
  expect,
  drupalInstallModule,
  drupalEnableTheme,
} from '../../drupal-fixtures';

const mainContent = '.region-content';
const mainMessagesContainer =
  '[data-drupal-messages] > .messages-list__wrapper';
const secondaryMessagesContainer = '[data-drupal-messages-other]';

const mainButtons = {
  addStatus: '#add--status',
  addError: '#add--error',
  addWarning: '#add--warning',
};

const secondaryButtons = {
  addStatus: '[id="add-[data-drupal-messages-other]-status"]',
  addError: '[id="add-[data-drupal-messages-other]-error"]',
  addWarning: '[id="add-[data-drupal-messages-other]-warning"]',
};

test.describe('Claro Drupal messages', { tag: ['@core', '@claro'] }, () => {
  test('Verify default placement of javascript-created messages', async ({
    drupalPage: page,
  }) => {
    await drupalInstallModule(page, 'js_message_test');
    await drupalEnableTheme(page, 'Claro');

    await page.goto('./js_message_test_link_with_system_messages');
    await page.locator(mainContent).waitFor({ state: 'visible' });
    await expect(page.locator(mainMessagesContainer)).toBeAttached();

    // 3 messages on page load from \Drupal::messenger().
    await expect(
      page.locator(`${mainMessagesContainer} > .messages-list__item`),
    ).toHaveCount(3);
    await expect(
      page.locator(`${mainMessagesContainer} > .messages--status`),
    ).toHaveCount(1);
    await expect(
      page.locator(`${mainMessagesContainer} > .messages--warning`),
    ).toHaveCount(1);
    await expect(
      page.locator(`${mainMessagesContainer} > .messages--error`),
    ).toHaveCount(1);

    // Trigger new messages via javascript.
    await page.locator(mainButtons.addStatus).click();
    await page.locator(mainButtons.addWarning).click();
    await page.locator(mainButtons.addError).click();

    // 6 total messages, 2 of each type.
    await expect(
      page.locator(`${mainMessagesContainer} > .messages-list__item`),
    ).toHaveCount(6);
    await expect(
      page.locator(`${mainMessagesContainer} > .messages--status`),
    ).toHaveCount(2);
    await expect(
      page.locator(`${mainMessagesContainer} > .messages--warning`),
    ).toHaveCount(2);
    await expect(
      page.locator(`${mainMessagesContainer} > .messages--error`),
    ).toHaveCount(2);
  });

  test('Verify customized placement of javascript-created messages', async ({
    drupalPage: page,
  }) => {
    await drupalInstallModule(page, 'js_message_test');
    await drupalEnableTheme(page, 'Claro');

    await page.goto('./js_message_test_link_with_system_messages');
    await page.locator(mainContent).waitFor({ state: 'visible' });
    await expect(page.locator(secondaryMessagesContainer)).toBeAttached();

    // 0 messages in secondary container on page load.
    await expect(
      page.locator(`${secondaryMessagesContainer} > .messages-list__item`),
    ).toHaveCount(0);

    // Trigger new messages via javascript.
    await page.locator(secondaryButtons.addStatus).click();
    await page.locator(secondaryButtons.addWarning).click();
    await page.locator(secondaryButtons.addError).click();

    // 3 total messages, 1 of each type.
    await expect(
      page.locator(`${secondaryMessagesContainer} > .messages-list__item`),
    ).toHaveCount(3);
    await expect(
      page.locator(`${secondaryMessagesContainer} > .messages--status`),
    ).toHaveCount(1);
    await expect(
      page.locator(`${secondaryMessagesContainer} > .messages--warning`),
    ).toHaveCount(1);
    await expect(
      page.locator(`${secondaryMessagesContainer} > .messages--error`),
    ).toHaveCount(1);
  });
});
