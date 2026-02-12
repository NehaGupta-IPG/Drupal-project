import {
  test,
  expect,
  drupalCreateUser,
  drupalLogin,
} from '../../drupal-fixtures';

const selectors = {
  schemePicker: '[data-drupal-selector="edit-color-scheme"]',
  primaryColor: {
    text: 'input[type="text"][name="base_primary_color"]',
    color: 'input[type="color"][name="base_primary_color_visual"]',
  },
  submit: '[data-drupal-selector="edit-submit"]',
  siteHeader: '.site-header__initial',
};

const colorSchemes = {
  default: { base_primary_color: '#1b9ae4' },
  firehouse: { base_primary_color: '#a30f0f' },
  ice: { base_primary_color: '#57919e' },
  plum: { base_primary_color: '#7a4587' },
  slate: { base_primary_color: '#47625b' },
};

test.use({
  drupalInstallOptions: {
    setupFile:
      'core/tests/Drupal/TestSite/TestSiteOliveroInstallTestScript.php',
    installProfile: 'minimal',
  },
});

test.describe('Olivero color settings', { tag: ['@core', '@olivero'] }, () => {
  test.beforeEach(async ({ drupalPage: page }) => {
    await drupalCreateUser(page, {
      name: 'user',
      password: '123',
      permissions: ['administer themes', 'view the administration theme'],
    });
    await drupalLogin(page, { name: 'user', password: '123' });
  });

  test('Color schemes update individual values', async ({
    drupalPage: page,
  }) => {
    await page.goto('/admin/appearance/settings/olivero');
    await page.locator(selectors.schemePicker).waitFor({ state: 'visible' });

    for (const [scheme, colors] of Object.entries(colorSchemes)) {
      if (scheme === 'default') {
        // eslint-disable-next-line no-continue
        continue;
      }
      await page.locator(selectors.schemePicker).selectOption(scheme);
      await expect(page.locator(selectors.primaryColor.text)).toHaveValue(
        colors.base_primary_color,
      );
      await expect(page.locator(selectors.primaryColor.color)).toHaveValue(
        colors.base_primary_color,
      );
    }

    // Switch back to default.
    await page.locator(selectors.schemePicker).selectOption('default');
    await expect(page.locator(selectors.primaryColor.text)).toHaveValue(
      colorSchemes.default.base_primary_color,
    );
    await expect(page.locator(selectors.primaryColor.color)).toHaveValue(
      colorSchemes.default.base_primary_color,
    );
  });

  test('Color inputs stay synchronized', async ({ drupalPage: page }) => {
    await page.goto('/admin/appearance/settings/olivero');
    await page
      .locator(selectors.primaryColor.text)
      .waitFor({ state: 'visible' });

    const testColors = ['#ff0000', '#00ff00', '#0000ff'];
    for (const color of testColors) {
      await page.locator(selectors.primaryColor.text).fill(color);
      await expect(page.locator(selectors.primaryColor.color)).toHaveValue(
        color,
      );
    }
  });

  test('Color selections impact olivero theme', async ({
    drupalPage: page,
  }) => {
    await page.goto('/admin/appearance/settings/olivero');
    await page
      .locator(selectors.primaryColor.color)
      .waitFor({ state: 'visible' });
    await page.locator(selectors.primaryColor.text).fill('#ff0000');
    await page.locator(selectors.submit).click();
    await page
      .locator(selectors.primaryColor.color)
      .waitFor({ state: 'visible' });

    await page.goto('/');
    await page.locator(selectors.siteHeader).waitFor({ state: 'visible' });
    await expect(page.locator(selectors.siteHeader)).toHaveCSS(
      'background-color',
      'rgb(255, 0, 0)',
    );

    // Change to plum color and verify.
    await page.goto('/admin/appearance/settings/olivero');
    await page
      .locator(selectors.primaryColor.color)
      .waitFor({ state: 'visible' });
    await page.locator(selectors.primaryColor.text).fill('#7a4587');
    await page.locator(selectors.submit).click();
    await page
      .locator(selectors.primaryColor.color)
      .waitFor({ state: 'visible' });

    await page.goto('/');
    await page.locator(selectors.siteHeader).waitFor({ state: 'visible' });
    await expect(page.locator(selectors.siteHeader)).toHaveCSS(
      'background-color',
      'rgb(122, 69, 135)',
    );
  });
});
