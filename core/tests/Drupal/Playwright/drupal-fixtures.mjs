import { test as base, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

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
export const test = base.extend({
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

export { expect };

/**
 * Log in as admin via one-time login link.
 */
export async function drupalLoginAsAdmin(page) {
  const userLink = testSiteCommand(
    `user-login 1 --site-path ${page._drupal.sitePath}`,
  );
  await page.goto(userLink);
}

/**
 * Log out of Drupal.
 */
export async function drupalLogout(page) {
  await page.goto('/user/logout/confirm');
  await page.locator('#user-logout-confirm').locator('[type="submit"]').click();
}

/**
 * Create a Drupal user with given permissions via the admin UI.
 */
export async function drupalCreateUser(
  page,
  { name, password, permissions = [] },
) {
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
    for (const perm of permissions) {
      await page.locator(`input[name="${machineName}[${perm}]"]`).check();
    }
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

  await page.getByRole('button', { name: 'Create new account' }).click();
  await expect(page.locator('[data-drupal-messages]')).toContainText(
    'Created a new user account',
  );
  await drupalLogout(page);
}

/**
 * Log in as a given user via the login form.
 */
export async function drupalLogin(page, { name, password }) {
  await page.goto('/user/login');
  await page.locator('input[name="name"]').fill(name);
  await page.locator('input[name="pass"]').fill(password);
  await page.locator('#user-login-form').locator('[type="submit"]').click();
  // Verify login succeeded by checking for session cookie.
  const cookies = await page.context().cookies();
  const hasSession = cookies.some((c) => /^S?SESS/.test(c.name));
  expect(hasSession).toBe(true);
}

/**
 * Install a module via the admin UI.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} module - The module machine name.
 * @param {boolean} [force=false] - Click Continue if dependencies are required.
 */
export async function drupalInstallModule(page, module, force = false) {
  await drupalLoginAsAdmin(page);
  await page.goto('/admin/modules');
  // Filter the module list to expand collapsed <details> elements.
  await page
    .locator('form.system-modules [data-drupal-selector="edit-text"]')
    .fill(module);
  const checkbox = page.locator(
    `form.system-modules [name="modules[${module}][enable]"]`,
  );
  await checkbox.waitFor({ state: 'visible' });
  await checkbox.check();
  await page.locator('form.system-modules').locator('[type="submit"]').click();

  if (force) {
    // Click Continue if a dependency confirmation form appears.
    const continueButton = page.locator('input[value="Continue"]');
    try {
      await continueButton.click({ timeout: 10000 });
    } catch {
      // No confirmation form — module installed directly.
    }
  }

  // Wait for the checkbox to be disabled, indicating the module is enabled.
  // Use 'attached' since the checkbox may be in a collapsed <details> element.
  await page
    .locator(`form.system-modules [name="modules[${module}][enable]"]:disabled`)
    .waitFor({ state: 'attached', timeout: 10000 });
  await drupalLogout(page);
}

/**
 * Assert that a specific deprecation error exists in sessionStorage.
 */
export async function assertDeprecationErrorExists(page, expected) {
  const warnings = await page.evaluate(() =>
    window.sessionStorage.getItem('js_testing_log_test.warnings'),
  );
  const entries = warnings ? JSON.parse(warnings) : [];
  const deprecations = (entries || [])
    .filter((m) => m.includes('[Deprecation]'))
    .map((m) => m.replace('[Deprecation] ', ''));
  expect(deprecations).toContainEqual(expect.stringContaining(expected));
}

/**
 * Enable a theme as the default via the admin UI.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} themeName - The theme display name (e.g. 'Claro').
 */
export async function drupalEnableTheme(page, themeName) {
  await drupalLoginAsAdmin(page);
  await page.goto('/admin/appearance');
  await page.locator(`[title="Install ${themeName} as default theme"]`).click();
  await page
    .locator('.system-themes-list')
    .first()
    .waitFor({ state: 'visible' });
  await drupalLogout(page);
}

/**
 * Assert no JS deprecation errors in sessionStorage.
 */
export async function assertNoDeprecationErrors(page) {
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
}
