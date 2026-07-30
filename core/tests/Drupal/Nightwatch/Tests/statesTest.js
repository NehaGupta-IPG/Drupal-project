module.exports = {
  '@tags': ['core'],
  before(browser) {
    browser.drupalInstall().drupalInstallModule('form_test', true);
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Test form with state API': (browser) => {
    browser
      .drupalRelativeURL('/form-test/javascript-states-form')
      .waitForElementVisible('body', 1000)
      .waitForElementNotVisible('input[name="textfield"]', 1000)
      .assert.noDeprecationErrors();
  },
  'Test number trigger with spinner widget': (browser) => {
    browser
      .drupalRelativeURL('/form-test/javascript-states-form')
      .waitForElementVisible('body', 1000)
      .waitForElementNotVisible(
        '#edit-item-visible-when-number-trigger-filled-by-spinner',
        1000,
      )
      .execute(() => {
        // Emulate usage of the spinner browser widget on number inputs
        // on modern browsers.
        const numberTrigger = document.getElementById('edit-number-trigger');
        numberTrigger.value = 1;
        numberTrigger.dispatchEvent(new Event('change'));
      });

    browser.waitForElementVisible(
      '#edit-item-visible-when-number-trigger-filled-by-spinner',
      1000,
    );
  },
  'Test custom element empty trigger with string value': (browser) => {
    // A <my-input> custom element exposes .value as a string and dispatches a
    // native change event. The States API empty trigger should correctly detect
    // filled/empty state via element[0].value rather than jQuery .val().
    browser
      .drupalRelativeURL('/form-test/javascript-states-form')
      .waitForElementVisible('body', 1000)
      .waitForElementNotVisible('#edit-item-visible-when-my-input-filled', 1000)
      .execute(() => {
        document.getElementById('edit-my-input-trigger').value = 'hello';
      });

    browser
      .waitForElementVisible('#edit-item-visible-when-my-input-filled', 1000)
      .execute(() => {
        document.getElementById('edit-my-input-trigger').value = '';
      });

    browser.waitForElementNotVisible(
      '#edit-item-visible-when-my-input-filled',
      1000,
    );
  },
  'Test custom element empty trigger with array value': (browser) => {
    // A <search-field> custom element exposes .value as an array. The States
    // API empty trigger should handle array values: [] is empty, [...] is not.
    browser
      .drupalRelativeURL('/form-test/javascript-states-form')
      .waitForElementVisible('body', 1000)
      .waitForElementNotVisible(
        '#edit-item-visible-when-search-field-filled',
        1000,
      )
      .execute(() => {
        document.getElementById('edit-search-field-trigger').value = ['foo'];
      });

    browser
      .waitForElementVisible(
        '#edit-item-visible-when-search-field-filled',
        1000,
      )
      .execute(() => {
        document.getElementById('edit-search-field-trigger').value = [];
      });

    browser.waitForElementNotVisible(
      '#edit-item-visible-when-search-field-filled',
      1000,
    );
  },
  'Test state:disabled on custom element': (browser) => {
    // A <my-input> custom element used as a dependent: state:disabled should
    // set element.disabled directly since it is not in tagsSupportDisable.
    // Our custom element reflects the disabled property as a 'disabled'
    // attribute. We use hasAttribute / not.hasAttribute because WebDriver
    // reads the boolean IDL property (.disabled) and returns "true" rather
    // than the empty-string content attribute set by setAttribute('disabled',
    // ''), so attributeEquals(..., '') would fail.
    browser
      .drupalRelativeURL('/form-test/javascript-states-form')
      .waitForElementVisible('body', 1000)
      // Initially unchecked → my-element should be disabled.
      .assert.hasAttribute(
        '#edit-my-element-disabled',
        'disabled',
        'my-input has disabled attribute when checkbox unchecked',
      )
      // Check the checkbox → my-element should become enabled.
      .click('input[name="checkbox_enable_my_element"]')
      .assert.not.hasAttribute(
        '#edit-my-element-disabled',
        'disabled',
        'my-input does not have disabled attribute when checkbox checked',
      );
  },
};
