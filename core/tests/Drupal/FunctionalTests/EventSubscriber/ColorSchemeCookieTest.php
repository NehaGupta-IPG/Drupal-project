<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\EventSubscriber;

use Drupal\Core\EventSubscriber\ColorSchemeCookieSubscriber;
use Drupal\dynamic_page_cache\EventSubscriber\DynamicPageCacheSubscriber;
use Drupal\page_cache\StackMiddleware\PageCache;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests how the color scheme preference of an account reaches the client.
 *
 * Every page renders with data-color-scheme="auto" on the <html> element, so
 * the markup is the same for every account. An account that picked 'light' or
 * 'dark' gets that value in a cookie instead, and a script in the head applies
 * it before the stylesheets are requested. 'auto' is what the server already
 * rendered, so it is expressed by the absence of the cookie.
 *
 * Only a response that changes the cookie is uncacheable. The subscriber
 * compares the cookie on the request with the preference of the account and
 * returns without touching the response when they already agree, which is what
 * keeps a Set-Cookie header off every cacheable response.
 *
 * The attribute in the markup is asserted in HtmlAttributesTest, and that the
 * script applies the cookie in the browser in ColorSchemeScriptTest.
 *
 * @see \Drupal\Core\EventSubscriber\ColorSchemeCookieSubscriber
 * @see \Drupal\Core\Render\BareHtmlPageRenderer::systemPageAttachments()
 * @see \Drupal\Tests\system\Functional\Theme\HtmlAttributesTest
 * @see \Drupal\FunctionalJavascriptTests\Core\ColorSchemeScriptTest
 */
#[Group('EventSubscriber')]
#[RunTestsInSeparateProcesses]
class ColorSchemeCookieTest extends BrowserTestBase {

  /**
   * A page that both page caches store, so that cacheability is observable.
   *
   * @see \Drupal\Tests\dynamic_page_cache\Functional\DynamicPageCacheIntegrationTest
   */
  const CACHEABLE_PATH = 'dynamic-page-cache-test/html';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dynamic_page_cache',
    'dynamic_page_cache_test',
    'page_cache',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Let the internal page cache report hits and misses.
    $this->config('system.performance')->set('cache.page.max_age', 300)->save();
  }

  /**
   * Tests that logging in seeds the cookie and logging out removes it.
   *
   * Nothing put the preference in this browser, so the cookie is written on
   * the response to the login. That is what makes the preference follow an
   * account to a browser it never opened its profile form in.
   */
  public function testCookieIsSeededOnLoginAndClearedOnLogout(): void {
    $light = $this->drupalCreateUser();
    $light->set('color_scheme', 'light')->save();
    $dark = $this->drupalCreateUser();
    $dark->set('color_scheme', 'dark')->save();
    $auto = $this->drupalCreateUser();

    foreach (['light' => $light, 'dark' => $dark] as $expected => $account) {
      $this->drupalLogin($account);
      $this->assertSame($expected, $this->getColorSchemeCookie());
      $this->drupalLogout();
      $this->assertNull($this->getColorSchemeCookie());
    }

    // 'auto' is what the server already rendered, so it costs no cookie.
    $this->drupalLogin($auto);
    $this->assertNull($this->getColorSchemeCookie());
    $this->drupalLogout();
    $this->assertNull($this->getColorSchemeCookie());
  }

  /**
   * Tests that an anonymous page carries no cookie and stays cacheable.
   */
  public function testAnonymousPagesStayCacheable(): void {
    // An anonymous account has no preference, so there is nothing to write and
    // the internal page cache stores the response.
    $this->drupalGet(self::CACHEABLE_PATH);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertNull($this->getColorSchemeCookie());
    $this->assertNoColorSchemeSetCookie();
    $this->assertSession()->responseHeaderEquals(PageCache::HEADER, 'MISS');

    // A stored response is replayed to every anonymous visitor, so it must not
    // carry a Set-Cookie header. Nothing runs on a hit of the internal page
    // cache, so the header can only be there if it was stored, which the kill
    // switch prevents.
    $this->drupalGet(self::CACHEABLE_PATH);
    $this->assertSession()->responseHeaderEquals(PageCache::HEADER, 'HIT');
    $this->assertSession()->responseHeaderDoesNotExist('Set-Cookie');
    $this->assertNull($this->getColorSchemeCookie());
  }

  /**
   * Tests that only a response that changes the cookie is uncacheable.
   *
   * The cookie belongs to one account, so a response that writes it must not
   * be stored. A response that leaves the cookie alone has nothing account
   * specific about it, and the same page therefore stays cacheable. The three
   * requests below are to the same URL and only differ in whether the cookie
   * on the request agrees with the preference of the account.
   */
  public function testKillSwitchFiresOnlyOnAMismatch(): void {
    $account = $this->drupalCreateUser();
    $account->set('color_scheme', 'dark')->save();
    $this->drupalLogin($account);
    $this->assertSame('dark', $this->getColorSchemeCookie());

    // Take the browser out of step with the account.
    $account->set('color_scheme', 'light')->save();

    // The cookie on the request is no longer what the account asks for, so the
    // response writes it and the kill switch denies the response policy of
    // Dynamic Page Cache.
    $this->drupalGet(self::CACHEABLE_PATH);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals(DynamicPageCacheSubscriber::HEADER, 'UNCACHEABLE (response policy)');
    $this->assertColorSchemeSetCookie('light');
    $this->assertSame('light', $this->getColorSchemeCookie());

    // The browser and the account agree again, so the very same page is left
    // alone and stored.
    $this->drupalGet(self::CACHEABLE_PATH);
    $this->assertSession()->responseHeaderEquals(DynamicPageCacheSubscriber::HEADER, 'MISS');
    $this->assertNoColorSchemeSetCookie();

    // Serving it from the cache confirms that it really was stored.
    $this->drupalGet(self::CACHEABLE_PATH);
    $this->assertSession()->responseHeaderEquals(DynamicPageCacheSubscriber::HEADER, 'HIT');
    $this->assertNoColorSchemeSetCookie();
  }

  /**
   * Tests the script that applies the cookie in the head of the page.
   *
   * The server always renders 'auto', so a test without JavaScript cannot
   * observe the override itself. What it can observe is that the script is
   * shipped, that it is in the head before the stylesheets are requested, and
   * that it only acts on the two values the cookie can carry.
   *
   * @see \Drupal\FunctionalJavascriptTests\Core\ColorSchemeScriptTest
   */
  public function testColorSchemeScript(): void {
    $this->drupalGet(self::CACHEABLE_PATH);
    $this->assertSession()->statusCodeEquals(200);

    // The script reads the cookie the subscriber writes.
    $content = $this->getSession()->getPage()->getContent();
    $script_position = strpos($content, "var n = '" . ColorSchemeCookieSubscriber::COOKIE_NAME . "=';");
    $this->assertNotFalse($script_position, 'The color scheme script is in the response.');

    // It only overrides the rendered attribute for the two values the cookie
    // can carry, so a missing or a manipulated cookie leaves 'auto' in place.
    $this->assertSession()->responseContains("if (s === 'light' || s === 'dark') {");
    $this->assertSession()->responseContains("document.documentElement.setAttribute('data-color-scheme', s);");

    // The script is in the head, and before the stylesheets, so the client
    // does not request the stylesheets of the wrong color scheme first.
    $head_end = strpos($content, '</head>');
    $this->assertNotFalse($head_end, 'The response has a head element.');
    $this->assertLessThan($head_end, $script_position);
    $stylesheet_position = strpos($content, 'rel="stylesheet"');
    $this->assertNotFalse($stylesheet_position, 'The response loads a stylesheet.');
    $this->assertLessThan($stylesheet_position, $script_position);
  }

  /**
   * Returns the color scheme cookie the browser holds.
   *
   * @return string|null
   *   The value of the cookie, or NULL if the browser holds no such cookie.
   */
  protected function getColorSchemeCookie(): ?string {
    return $this->getSession()->getCookie(ColorSchemeCookieSubscriber::COOKIE_NAME);
  }

  /**
   * Asserts that the last response writes the color scheme cookie.
   *
   * @param string $expected
   *   The value the cookie is expected to be set to.
   */
  protected function assertColorSchemeSetCookie(string $expected): void {
    $set_cookies = $this->getColorSchemeSetCookieHeaders();
    $this->assertCount(1, $set_cookies, 'The response writes the color scheme cookie once.');
    $set_cookie = $set_cookies[0];
    $this->assertStringStartsWith(ColorSchemeCookieSubscriber::COOKIE_NAME . '=' . $expected . ';', $set_cookie);
    // The script in the head reads the cookie, so it is not HTTP only.
    $this->assertStringNotContainsStringIgnoringCase('httponly', $set_cookie);
    $this->assertStringContainsStringIgnoringCase('samesite=lax', $set_cookie);
  }

  /**
   * Asserts that the last response leaves the color scheme cookie alone.
   */
  protected function assertNoColorSchemeSetCookie(): void {
    $this->assertSame([], $this->getColorSchemeSetCookieHeaders());
  }

  /**
   * Returns the Set-Cookie headers of the last response for the cookie.
   *
   * @return string[]
   *   The values of the Set-Cookie header that name the color scheme cookie.
   *   A response can hold more than one Set-Cookie header.
   */
  protected function getColorSchemeSetCookieHeaders(): array {
    // A response can hold more than one Set-Cookie header, and the name of the
    // header itself is not case sensitive.
    $headers = array_change_key_case($this->getSession()->getResponseHeaders());
    $set_cookies = $headers['set-cookie'] ?? [];
    $set_cookies = is_array($set_cookies) ? $set_cookies : [$set_cookies];
    $prefix = ColorSchemeCookieSubscriber::COOKIE_NAME . '=';
    $names_the_cookie = static fn (string $set_cookie): bool => str_starts_with($set_cookie, $prefix);
    return array_values(array_filter($set_cookies, $names_the_cookie));
  }

}
