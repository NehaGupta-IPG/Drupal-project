<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Theme;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests attributes inserted in the 'html' and 'body' elements on the page.
 */
#[Group('Theme')]
#[RunTestsInSeparateProcesses]
class HtmlAttributesTest extends BrowserTestBase {

  use AssertPageCacheContextsAndTagsTrait;

  /**
   * The expected markup of the html element.
   *
   * The attributes appear in the order they are set on the Attribute object:
   * ThemePreprocess::preprocessHtml() sets 'lang', 'dir' and
   * 'data-color-scheme', and theme_test appends its own attribute in a later
   * hook_preprocess_html() implementation. A module preprocess function runs
   * for every theme, so this is the markup of every theme used here.
   *
   * @see \Drupal\Core\Theme\ThemePreprocess::preprocessHtml()
   * @see \Drupal\theme_test\Hook\ThemeTestHooks::preprocessHtml()
   */
  const HTML_ELEMENT = '<html lang="en" dir="ltr" data-color-scheme="auto" theme_test_html_attribute="theme test html attribute value">';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['theme_test', 'test_page_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that attributes in the 'html' and 'body' elements can be altered.
   */
  public function testThemeHtmlAttributes(): void {
    $this->drupalGet('');
    $this->assertSession()->responseContains(self::HTML_ELEMENT);
    $this->assertSession()->elementsCount('xpath', '/body[@theme_test_body_attribute="theme test body attribute value"]', 1);
  }

  /**
   * Tests that the data-color-scheme attribute does not depend on the theme.
   *
   * The attribute is always 'auto', whether or not the active theme declares
   * 'supports color scheme' in its .info.yml file, and whatever the account
   * stored as its preference. A customized preference reaches the client in a
   * cookie instead, and a script in the head applies it. That keeps the markup
   * the same for every account, so the page declares no cacheability for it.
   *
   * @param string $theme
   *   The theme to install and use as the default theme.
   *
   * @see \Drupal\Core\EventSubscriber\ColorSchemeCookieSubscriber
   * @see \Drupal\FunctionalTests\EventSubscriber\ColorSchemeCookieTest
   */
  #[DataProvider('providerColorSchemeAttributeIsInvariant')]
  public function testColorSchemeAttributeIsInvariant(string $theme): void {
    \Drupal::service('theme_installer')->install([$theme]);
    $this->config('system.theme')->set('default', $theme)->save();

    // An anonymous account has no preference to store.
    $this->drupalGet('test-page');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertColorSchemeAttribute();

    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);
    foreach (['auto', 'light', 'dark'] as $color_scheme) {
      $account->set('color_scheme', $color_scheme)->save();
      $this->drupalGet('test-page');
      $this->assertSession()->statusCodeEquals(200);
      $this->assertColorSchemeAttribute();
    }
  }

  /**
   * Provides test cases for ::testColorSchemeAttributeIsInvariant().
   *
   * @return array[]
   *   An array of test cases.
   */
  public static function providerColorSchemeAttributeIsInvariant(): array {
    return [
      'theme declaring support' => ['test_color_scheme_theme'],
      'subtheme inheriting support' => ['test_color_scheme_subtheme'],
      'theme not declaring support' => ['stark'],
    ];
  }

  /**
   * Asserts the color scheme attribute and that it declares no cacheability.
   */
  protected function assertColorSchemeAttribute(): void {
    $this->assertSession()->responseContains(self::HTML_ELEMENT);
    $this->assertSession()->responseNotContains('data-color-scheme="light"');
    $this->assertSession()->responseNotContains('data-color-scheme="dark"');

    // The markup is the same for every account, so there is no cache context
    // for the preference.
    $this->assertNoCacheContext('user.color_scheme');
    // Nothing reads the theme configuration while the page is rendered, so the
    // page does not depend on it either.
    $this->assertNotContains('config:system.theme', $this->getCacheHeaderValues('X-Drupal-Cache-Tags'));
  }

}
