const argv = require('minimist')(process.argv.slice(2));

const adminTest = {
  '@tags': ['core', 'a11y', 'a11y:admin'],

  before(browser) {
    browser.drupalInstall({ installProfile: 'standard' });
    // If an admin theme other than Claro is being used for testing, install it.
    if (argv.adminTheme && argv.adminTheme !== browser.globals.adminTheme) {
      browser.drupalEnableTheme(argv.adminTheme, true);
    }
  },
  after(browser) {
    browser.drupalUninstall();
  },
};
const testCases = [
  {
    name: 'User Edit',
    path: '/user/1/edit',
    // @todo remove the disabled 'region' rule in https://drupal.org/i/3318396.
    options: {
      rules: {
        'landmark-unique': { enabled: false },
        region: { enabled: false },
      },
    },
  },
  {
    name: 'Create Article',
    path: '/node/add/article?destination=/admin/content',
    // @todo remove the disabled 'region' rule in https://drupal.org/i/3318396.
    options: {
      rules: {
        'landmark-unique': { enabled: false },
        region: { enabled: false },
      },
    },
  },
  {
    name: 'Create Page',
    path: '/node/add/page?destination=/admin/content',
    // @todo remove the disabled 'region' rule in https://drupal.org/i/3318396.
    options: {
      rules: {
        'landmark-unique': { enabled: false },
        region: { enabled: false },
      },
    },
  },
  {
    name: 'Content Page',
    path: '/admin/content',
    // @todo remove the disabled 'region' rule in https://drupal.org/i/3318396.
    options: {
      rules: {
        'empty-table-header': { enabled: false },
        'landmark-unique': { enabled: false },
        region: { enabled: false },
      },
    },
  },
  {
    name: 'Structure Page',
    path: '/admin/structure',
    // @todo remove the disabled 'region' rule in https://drupal.org/i/3318396.
    options: {
      rules: {
        'landmark-unique': { enabled: false },
        region: { enabled: false },
      },
    },
  },
  {
    name: 'Add content type',
    path: '/admin/structure/types/add',
    // @todo remove the disabled 'region' rule in https://drupal.org/i/3318396.
    options: {
      rules: {
        'landmark-unique': { enabled: false },
        region: { enabled: false },
      },
    },
  },
  {
    name: 'Add vocabulary',
    path: '/admin/structure/taxonomy/add',
    // @todo remove the disabled 'region' rule in https://drupal.org/i/3318396.
    options: {
      rules: {
        'empty-table-header': { enabled: false },
        'landmark-unique': { enabled: false },
        region: { enabled: false },
      },
    },
  },
  {
    // Tests long breadcrumb for https://drupal.org/i/3223147.
    name: 'Manage text format, mobile',
    path: '/admin/config/content/formats/manage/restricted_html',
    windowSize: {
      // Dimensions used by Lighthouse for mobile.
      width: 415,
      height: 823,
    },
    options: {
      rules: {
        'landmark-unique': { enabled: false },
        region: { enabled: false },
      },
      runOnly: {
        type: 'tag',
        values: [
          'wcag2a',
          'wcag2aa',
          'wcag21a',
          'wcag21aa',
          'best-practice',
          'wcag22a',
          'wcag22aa',
        ],
      },
    },
  },
  {
    name: 'Structure | Block',
    path: '/admin/structure/block',
    // @todo remove the disabled 'region' rule in https://drupal.org/i/3318396.
    // @todo remove the skipped rules below in https://drupal.org/i/3318394.
    options: {
      rules: {
        'color-contrast': { enabled: false },
        'duplicate-id-active': { enabled: false },
        'landmark-unique': { enabled: false },
        region: { enabled: false },
      },
    },
  },
];

testCases.forEach((testCase) => {
  adminTest[`Accessibility - Admin Theme: ${testCase.name}`] = (browser) => {
    if (testCase.windowSize) {
      browser.setWindowSize(
        testCase.windowSize.width,
        testCase.windowSize.height,
      );
    }

    browser.drupalLoginAsAdmin(() => {
      browser
        .drupalRelativeURL(testCase.path)
        .axeInject()
        .axeRun('body', testCase.options || {});
    });
  };
});

module.exports = adminTest;
