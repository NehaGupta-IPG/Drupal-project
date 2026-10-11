module.exports = {
  '@tags': ['core'],
  before(browser) {
    browser.drupalInstall();
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Test clicking anchor in WYSIWYG editor': (browser) => {
    const mainContentSelector = '#main-content';
    const wysiwygSelector = '.ck-content[contenteditable="true"]';
    const testAnchorSelector = `${wysiwygSelector} a[href="#main-content"]`;
    let mainContentTopPosition;
    browser.drupalLoginAsAdmin(() => {
      // Navigate to the node creation page and wait for WYSIWYG editor to load.
      browser
        .drupalRelativeURL('/node/add/page')
        .waitForElementVisible(wysiwygSelector, 1000);
      // Scroll down past the #main-content element and store the top position
      // of #main-content (in relation to the viewport) in a variable.
      mainContentTopPosition = browser.execute(
        function scrollToTargetAndGetTopPosition(mSelector, wSelector) {
          // The WYSIWYG editor is below the #main-content element, so we
          // scroll down to it. This ensures that #main-content is outside the
          // bounds of the viewport.
          wSelector.scrollIntoView();
          const mainContent = document.querySelector(mSelector);
          const rect = mainContent.getBoundingClientRect();
          return rect.top;
        },
        [mainContentSelector, wysiwygSelector],
      );
      // Focus on CKEditor WYSIWYG editor and type some text into it.
      browser
        .click(wysiwygSelector)
        .keys('Back to top'.split(''))
        // Select the text and press Ctrl+k to open up the link creation UI.
        .keys([browser.Keys.CONTROL, 'a'])
        .keys([browser.Keys.CONTROL, 'k'])
        // Enter #main-content into the href and press Return key to submit the
        // link creation form.
        .keys(mainContentSelector.split(''))
        .keys([browser.Keys.RETURN])
        // Wait for the link to appear in the WYSIWYG and click on it.
        .waitForElementVisible(testAnchorSelector, 1000)
        .click(testAnchorSelector);
      // Assert that the position of the top of #main-content is intact.
      const newMainContentTopPosition = browser.execute(
        function getTopPosition(mSelector) {
          const mainContent = document.querySelector(mSelector);
          const rect = mainContent.getBoundingClientRect();
          return rect.top;
        },
        [mainContentSelector],
      );
      browser.assert.ok(
        mainContentTopPosition === newMainContentTopPosition,
        'Clicking anchor link inside WYSIWYG should not cause browser to navigate to target.',
      );
    });
  },
};
