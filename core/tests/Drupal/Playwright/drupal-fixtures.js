const { test: base, expect } = require('@playwright/test');
const { execSync } = require('node:child_process');

// Run a shell command, optionally as the webserver user.
function testSiteCommand(command) {
  const cmd = process.env.DRUPAL_TEST_WEBSERVER_USER
    ? `sudo -u ${process.env.DRUPAL_TEST_WEBSERVER_USER} php scripts/test-site.php ${command}`
    : `php scripts/test-site.php ${command}`;
  return execSync(cmd).toString().trim();
}

/**
 * Install a Drupal test site and return a Page with the session cookie set.
 *
 * @param {import('@playwright/test').Browser} browser
 * @param {object} options
 * @param {string} [options.installProfile='nightwatch_testing']
 * @param {string} [options.setupFile='']
 * @param {string} [options.langcode='']
 * @return {{ page: import('@playwright/test').Page, dbPrefix: string }}
 */
async function drupalInstall(browser, options = {}) {
  const {
    installProfile = 'nightwatch_testing',
    setupFile = '',
    langcode = '',
  } = options;
  const dbOption = process.env.DRUPAL_TEST_DB_URL
    ? `--db-url "${process.env.DRUPAL_TEST_DB_URL}"`
    : '';
  const baseUrl = process.env.DRUPAL_TEST_BASE_URL;
  const setupFileOption = setupFile ? `--setup-file "${setupFile}"` : '';
  const langcodeOption = langcode ? `--langcode "${langcode}"` : '';

  const output = testSiteCommand(
    `install --install-profile "${installProfile}" ${setupFileOption} ${langcodeOption} --base-url ${baseUrl} ${dbOption} --json`,
  );

  const installData = JSON.parse(output);
  const dbPrefix = installData.db_prefix;
  const sitePath = installData.site_path;

  const context = await browser.newContext({ baseURL: baseUrl });
  await context.addCookies([
    {
      name: 'SIMPLETEST_USER_AGENT',
      value: encodeURIComponent(installData.user_agent),
      url: baseUrl,
    },
  ]);

  const page = await context.newPage();
  page._drupal = { dbPrefix, sitePath };
  return { page, dbPrefix };
}

/**
 * Tear down a Drupal test site.
 */
function drupalTearDown(dbPrefix) {
  const dbOption = process.env.DRUPAL_TEST_DB_URL
    ? `--db-url "${process.env.DRUPAL_TEST_DB_URL}"`
    : '';
  try {
    testSiteCommand(`tear-down ${dbPrefix} ${dbOption}`);
  } catch (e) {
    console.error('Failed to tear down test site:', e.message);
  }
}

/**
 * Extend Playwright's base test with Drupal-specific fixtures.
 */
exports.test = base.extend({
  /**
   * Install options for the drupalPage fixture. Override in test files via
   * test.use({ drupalInstallOptions: { ... } }) to customize the install.
   */
  drupalInstallOptions: [{}, { option: true }],

  /**
   * drupalPage fixture: installs a Drupal test site before each test
   * and tears it down after.
   *
   * Usage: test('my test', async ({ drupalPage }) => { ... })
   */
  drupalPage: async ({ browser, drupalInstallOptions }, use) => {
    const { page, dbPrefix } = await drupalInstall(
      browser,
      drupalInstallOptions,
    );

    await use(page);

    await page.context().close();
    drupalTearDown(dbPrefix);
  },
});

exports.expect = expect;

/**
 * Log in as admin via one-time login link.
 */
exports.drupalLoginAsAdmin = async (page) => {
  const userLink = testSiteCommand(
    `user-login 1 --site-path ${page._drupal.sitePath}`,
  );
  await page.goto(userLink);
};

/**
 * Create a Drupal user with given permissions via the admin UI.
 */
exports.drupalCreateUser = async (
  page,
  { name, password, permissions = [] },
) => {
  const { drupalLoginAsAdmin, drupalLogout } = exports;
  let machineName;
  if (permissions.length) {
    const roleLabel = Math.random()
      .toString(36)
      .replace(/[^\w\d]/g, '')
      .substring(2, 15);
    // Create role: login as admin, create role with permissions.
    await drupalLoginAsAdmin(page);
    await page.goto('/admin/people/roles/add');
    await page.locator('input[name="label"]').fill(roleLabel);
    // Trigger JS machine name generation and wait for it to appear.
    await page.locator('input[name="label"]').dispatchEvent('formUpdated');
    await page
      .locator('.user-role-form .machine-name-value')
      .waitFor({ state: 'visible' });
    machineName = await page
      .locator('.user-role-form .machine-name-value')
      .textContent();
    await page.locator('#user-role-form').locator('[type="submit"]').click();

    // Assign permissions.
    await page.goto('/admin/people/permissions');
    await page.locator('table.permissions').waitFor({ state: 'visible' });
    await Promise.all(
      permissions.map((perm) =>
        page.locator(`input[name="${machineName}[${perm}]"]`).check(),
      ),
    );
    await page
      .locator('#user-admin-permissions')
      .locator('[type="submit"]')
      .click();
    await drupalLogout(page);
  }

  // Create user: login as admin.
  await drupalLoginAsAdmin(page);
  await page.goto('/admin/people/create');
  await page.locator('input[name="name"]').fill(name);
  await page.locator('input[name="notify"]').uncheck();
  await page.locator('input[name="pass[pass1]"]').fill(password);
  await page.locator('input[name="pass[pass2]"]').fill(password);

  if (permissions.length && machineName) {
    await page.locator(`input[name="roles[${machineName}]"]`).check();
  }

  await page.locator('#user-register-form').locator('[type="submit"]').click();
  await expect(page.locator('[data-drupal-messages]')).toContainText(
    'Created a new user account',
  );
  await drupalLogout(page);
};

/**
 * Log in as a given user via the login form.
 */
exports.drupalLogin = async (page, { name, password }) => {
  await page.goto('/user/login');
  await page.locator('input[name="name"]').fill(name);
  await page.locator('input[name="pass"]').fill(password);
  await page.locator('#user-login-form').locator('[type="submit"]').click();
  // Verify login succeeded by checking for session cookie.
  const cookies = await page.context().cookies();
  const hasSession = cookies.some((c) => /^S?SESS/.test(c.name));
  expect(hasSession).toBe(true);
};

/**
 * Log out of Drupal.
 */
exports.drupalLogout = async (page) => {
  await page.goto('/user/logout/confirm');
  await page.locator('#user-logout-confirm').locator('[type="submit"]').click();
};

/**
 * Assert no JS deprecation errors in sessionStorage.
 */
exports.assertNoDeprecationErrors = async (page) => {
  const warnings = await page.evaluate(() =>
    window.sessionStorage.getItem('js_testing_log_test.warnings'),
  );
  if (warnings) {
    const entries = JSON.parse(warnings);
    const deprecations = (entries || []).filter((m) =>
      m.includes('[Deprecation]'),
    );
    expect(deprecations).toEqual([]);
  }
};
