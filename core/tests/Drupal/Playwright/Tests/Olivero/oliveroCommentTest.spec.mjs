import {
  test,
  expect,
  drupalCreateUser,
  drupalLogin,
} from '../../drupal-fixtures';

const commentCountSelector = 'h2.comments__title .comments__count';

test.use({
  drupalInstallOptions: {
    setupFile:
      'core/tests/Drupal/TestSite/TestSiteOliveroInstallTestScript.php',
    installProfile: 'minimal',
  },
});

test.describe('Olivero comments', { tag: ['@core', '@olivero'] }, () => {
  test('Article without comments should not display count', async ({
    drupalPage: page,
  }) => {
    await drupalCreateUser(page, {
      name: 'user',
      password: '123',
      permissions: [
        'access comments',
        'post comments',
        'skip comment approval',
      ],
    });
    await drupalLogin(page, { name: 'user', password: '123' });

    await page.goto('./node/1');
    await expect(page.locator('body')).toContainText(
      'Article without comments',
    );
    await expect(page.locator(commentCountSelector)).not.toBeAttached();
  });

  test('Article with comments should display count', async ({
    drupalPage: page,
  }) => {
    await drupalCreateUser(page, {
      name: 'user',
      password: '123',
      permissions: [
        'access comments',
        'post comments',
        'skip comment approval',
      ],
    });
    await drupalLogin(page, { name: 'user', password: '123' });

    await page.goto('./node/2');
    await expect(page.locator('body')).toContainText('Article with comments');
    await expect(page.locator('h2.comments__title')).toBeAttached();
    await expect(page.locator(commentCountSelector)).toBeAttached();
    await expect(page.locator(commentCountSelector)).toContainText('2');
  });
});
