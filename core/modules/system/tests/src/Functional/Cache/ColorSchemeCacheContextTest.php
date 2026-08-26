<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Cache;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the color scheme cache context follows the theme declaration.
 *
 * @see \Drupal\system\Hook\PageAttachmentsHook::pageAttachments()
 * @see \Drupal\Core\Theme\ColorSchemeSupport::appliesToAccount()
 * @see \Drupal\Core\Cache\Context\ColorSchemeCacheContext
 */
#[Group('Cache')]
#[RunTestsInSeparateProcesses]
class ColorSchemeCacheContextTest extends BrowserTestBase {

  use AssertPageCacheContextsAndTagsTrait;

  /**
   * A theme that declares 'supports color scheme'.
   */
  const SUPPORTING_THEME = 'test_color_scheme_theme';

  /**
   * A theme that does not declare 'supports color scheme'.
   */
  const PLAIN_THEME = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['test_page_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the presence of the 'user.color_scheme' cache context.
   *
   * The <html> element only carries a data-color-scheme attribute when the
   * active theme declares 'supports color scheme' in its .info.yml file, so the
   * cache context is only declared for those themes. The decision then rests on
   * the active theme alone, so the theme configuration is not a dependency of
   * it.
   *
   * @param string $theme
   *   The theme to install and use as the default theme.
   * @param bool $expected
   *   TRUE if the 'user.color_scheme' cache context is expected.
   */
  #[DataProvider('providerColorSchemeCacheContext')]
  public function testColorSchemeCacheContext(string $theme, bool $expected): void {
    \Drupal::service('theme_installer')->install([$theme]);
    $this->config('system.theme')->set('default', $theme)->save();

    $this->drupalGet('test-page');
    $this->assertSession()->statusCodeEquals(200);

    $contexts = $this->getCacheHeaderValues('X-Drupal-Cache-Contexts');
    if ($expected) {
      $this->assertContains('user.color_scheme', $contexts);
      $this->assertNotContains('config:system.theme', $this->getCacheHeaderValues('X-Drupal-Cache-Tags'));
    }
    else {
      $this->assertNotContains('user.color_scheme', $contexts);
    }
  }

  /**
   * Provides test cases for ::testColorSchemeCacheContext().
   *
   * @return array[]
   *   An array of test cases.
   */
  public static function providerColorSchemeCacheContext(): array {
    return [
      'theme declaring support' => [self::SUPPORTING_THEME, TRUE],
      'subtheme inheriting support' => ['test_color_scheme_subtheme', TRUE],
      'theme not declaring support' => [self::PLAIN_THEME, FALSE],
    ];
  }

  /**
   * Tests the context when only the administration theme declares support.
   *
   * A page of a theme without support carries administration theme output, so
   * the preference still has an effect for an account that may use the
   * administration theme. The decision then rests on the permission and on the
   * theme configuration, and both are declared as dependencies.
   *
   * @param bool $may_use_admin_theme
   *   TRUE to give the account the 'view the administration theme' permission.
   * @param bool $expected
   *   TRUE if the 'user.color_scheme' cache context is expected.
   */
  #[DataProvider('providerAdminThemeCacheContext')]
  public function testAdminThemeCacheContext(bool $may_use_admin_theme, bool $expected): void {
    \Drupal::service('theme_installer')->install([self::SUPPORTING_THEME]);
    $this->config('system.theme')
      ->set('default', self::PLAIN_THEME)
      ->set('admin', self::SUPPORTING_THEME)
      ->save();

    $this->drupalLogin($this->drupalCreateUser($may_use_admin_theme ? ['view the administration theme'] : []));

    // 'test-page' is not an administration route, so the site theme renders it.
    $this->drupalGet('test-page');
    $this->assertSession()->statusCodeEquals(200);

    $contexts = $this->getCacheHeaderValues('X-Drupal-Cache-Contexts');
    $tags = $this->getCacheHeaderValues('X-Drupal-Cache-Tags');
    // The permission decides the outcome, so the page varies by it either way.
    $this->assertContains('user.permissions', $contexts);
    if ($expected) {
      $this->assertContains('user.color_scheme', $contexts);
      $this->assertContains('config:system.theme', $tags);
    }
    else {
      $this->assertNotContains('user.color_scheme', $contexts);
      // Without the permission the administration theme is never read, so the
      // theme configuration is not a dependency of the decision.
      $this->assertNotContains('config:system.theme', $tags);
    }
  }

  /**
   * Provides test cases for ::testAdminThemeCacheContext().
   *
   * @return array[]
   *   An array of test cases.
   */
  public static function providerAdminThemeCacheContext(): array {
    return [
      'account may use the administration theme' => [TRUE, TRUE],
      'account may not use the administration theme' => [FALSE, FALSE],
    ];
  }

}
