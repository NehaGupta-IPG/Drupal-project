<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Theme;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests attributes inserted in the 'html' and 'body' elements on the page.
 */
#[Group('Theme')]
#[RunTestsInSeparateProcesses]
class HtmlAttributesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['theme_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that attributes in the 'html' and 'body' elements can be altered.
   */
  public function testThemeHtmlAttributes(): void {
    $this->drupalGet('');
    // Stark does not declare 'supports color scheme', so the html element does
    // not carry a data-color-scheme attribute.
    $this->assertSession()->responseContains('<html lang="en" dir="ltr" theme_test_html_attribute="theme test html attribute value">');
    $this->assertSession()->elementsCount('xpath', '/body[@theme_test_body_attribute="theme test body attribute value"]', 1);
  }

  /**
   * Tests that the data-color-scheme attribute follows the theme declaration.
   *
   * Only a theme that declares 'supports color scheme' in its .info.yml file
   * gets the attribute. Support is inherited from base themes, so a subtheme of
   * a supporting theme also gets it.
   *
   * @param string $theme
   *   The theme to install and use as the default theme.
   * @param bool $expected
   *   TRUE if the data-color-scheme attribute is expected on the html element.
   */
  #[DataProvider('providerColorSchemeAttribute')]
  public function testColorSchemeAttribute(string $theme, bool $expected): void {
    \Drupal::service('theme_installer')->install([$theme]);
    $this->config('system.theme')->set('default', $theme)->save();

    $account = $this->drupalCreateUser();
    $account->set('color_scheme', 'dark')->save();
    $this->drupalLogin($account);

    $this->drupalGet('');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertColorSchemeAttribute($expected);
  }

  /**
   * Provides test cases for ::testColorSchemeAttribute().
   *
   * @return array[]
   *   An array of test cases.
   */
  public static function providerColorSchemeAttribute(): array {
    return [
      'theme declaring support' => ['test_color_scheme_theme', TRUE],
      'subtheme inheriting support' => ['test_color_scheme_subtheme', TRUE],
      'theme not declaring support' => ['stark', FALSE],
    ];
  }

  /**
   * Tests the attribute when only the administration theme declares support.
   *
   * A page of a theme without support still carries administration theme
   * output, such as the navigation and its top bar. That output needs the
   * attribute on the <html> element, so the attribute is present for an
   * account that may use the administration theme, and absent for one that may
   * not.
   *
   * @param bool $may_use_admin_theme
   *   TRUE to give the account the 'view the administration theme' permission.
   * @param bool $expected
   *   TRUE if the data-color-scheme attribute is expected on the html element.
   */
  #[DataProvider('providerAdminThemeColorSchemeAttribute')]
  public function testAdminThemeColorSchemeAttribute(bool $may_use_admin_theme, bool $expected): void {
    \Drupal::service('theme_installer')->install(['test_color_scheme_theme']);
    $this->config('system.theme')
      ->set('default', 'stark')
      ->set('admin', 'test_color_scheme_theme')
      ->save();

    $account = $this->drupalCreateUser($may_use_admin_theme ? ['view the administration theme'] : []);
    $account->set('color_scheme', 'dark')->save();
    $this->drupalLogin($account);

    // The front page is not an administration route, so Stark renders it.
    $this->drupalGet('');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertColorSchemeAttribute($expected);
  }

  /**
   * Provides test cases for ::testAdminThemeColorSchemeAttribute().
   *
   * @return array[]
   *   An array of test cases.
   */
  public static function providerAdminThemeColorSchemeAttribute(): array {
    return [
      'account may use the administration theme' => [TRUE, TRUE],
      'account may not use the administration theme' => [FALSE, FALSE],
    ];
  }

  /**
   * Asserts the presence of the data-color-scheme attribute on <html>.
   *
   * @param bool $expected
   *   TRUE if the attribute is expected to be present, with the value 'dark'.
   */
  protected function assertColorSchemeAttribute(bool $expected): void {
    if ($expected) {
      $this->assertSession()->responseContains('<html lang="en" dir="ltr" data-color-scheme="dark" theme_test_html_attribute="theme test html attribute value">');
    }
    else {
      $this->assertSession()->responseContains('<html lang="en" dir="ltr" theme_test_html_attribute="theme test html attribute value">');
      $this->assertSession()->responseNotContains('data-color-scheme');
    }
  }

}
