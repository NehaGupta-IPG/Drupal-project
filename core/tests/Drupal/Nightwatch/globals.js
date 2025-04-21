const path = require('node:path');
const fs = require('node:fs');
const mkdirp = require('mkdirp');
const nightwatchSettings = require('./nightwatch.conf');

const commandAsWebserver = (command) => {
  if (process.env.DRUPAL_TEST_WEBSERVER_USER) {
    return `sudo -u ${process.env.DRUPAL_TEST_WEBSERVER_USER} ${command}`;
  }
  return command;
};

const oliveroTestThemes = {
  test_custom_theme: 'Test Custom Theme',
  olivero: 'Olivero',
};

const testPerTheme = (browser, fn) => {
  Object.entries(oliveroTestThemes).forEach(([theme, title]) => {
    // Button uses title, if available, machine name otherwise
    const setAsDefault = `a[title="Set ${theme} as default theme"], a[title="Set ${title} as default theme"]`;

    browser
      .drupalEnableTheme(theme, false)
      .drupalRelativeURL('/admin/appearance')
      .waitForElementVisible('main')
      .element('css selector', setAsDefault, (result) => {
        // Set current theme to default, if it isn't already.
        if (result.status !== -1) {
          browser.click(setAsDefault);
        }

        // Run the test on this theme.
        fn.call({}, browser, theme, title);
      });
  });
};

module.exports = {
  afterEach: (browser, done) => {
    // Writes the console log - used by the "logAndEnd" command.
    if (
      browser.drupalLogConsole &&
      (!browser.drupalLogConsoleOnlyOnError ||
        browser.currentTest.results.errors > 0 ||
        browser.currentTest.results.failed > 0)
    ) {
      const resultPath = path.join(
        __dirname,
        `../../../${nightwatchSettings.output_folder}/consoleLogs/${browser.currentTest.module}`,
      );
      const status =
        browser.currentTest.results.errors > 0 ||
        browser.currentTest.results.failed > 0
          ? 'FAILED'
          : 'PASSED';
      mkdirp.sync(resultPath);
      const now = new Date().toString().replace(/[\s]+/g, '-');
      const testName = (
        browser.currentTest.name || browser.currentTest.module
      ).replace(/[\s/]+/g, '-');
      browser
        .getLog('browser', (logEntries) => {
          const browserLog = JSON.stringify(logEntries, null, '  ');
          fs.writeFileSync(
            `${resultPath}/${testName}_${status}_${now}_console.json`,
            browserLog,
          );
        })
        .end(done);
    } else {
      browser.end(done);
    }
  },
  commandAsWebserver,
  testPerTheme,
};
