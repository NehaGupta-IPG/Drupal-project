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
 * @see \Drupal\Core\Cache\Context\ColorSchemeCacheContext
 */
#[Group('Cache')]
#[RunTestsInSeparateProcesses]
class ColorSchemeCacheContextTest extends BrowserTestBase {

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
   * theme declares 'supports color scheme' in its .info.yml file, so the cache
   * context is only declared for those themes.
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

    $contexts = explode(' ', $this->getSession()->getResponseHeader('X-Drupal-Cache-Contexts') ?? '');
    if ($expected) {
      $this->assertContains('user.color_scheme', $contexts);
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
      'theme declaring support' => ['test_color_scheme_theme', TRUE],
      'subtheme inheriting support' => ['test_color_scheme_subtheme', TRUE],
      'theme not declaring support' => ['stark', FALSE],
    ];
  }

}
