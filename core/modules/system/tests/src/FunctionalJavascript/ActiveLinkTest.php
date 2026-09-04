<?php

declare(strict_types=1);

namespace Drupal\Tests\system\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// cspell:ignore комацу
/**
 * Tests active link JS behavior.
 *
 * @see Drupal.behaviors.activeLinks
 */
#[Group('system')]
#[RunTestsInSeparateProcesses]
class ActiveLinkTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['common_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Ensures no JS error is thrown with query strings containing special chars.
   */
  public function testQueryStringQuotes(): void {
    $user = $this->createUser();
    $this->drupalLogin($user);
    $this->drupalGet($this->getSession()->getCurrentUrl(), ['query' => ['foo' => "\"'[](){}*+~>|\\/:;,.!@#$%^&-_=?<>"]]);
    $this->failOnJavaScriptErrors();
  }

  /**
   * Tests that active-link.js reads the query string from window.location.
   */
  public function testQueryStringFromWindowLocation(): void {
    $this->drupalLogin($this->createUser());

    // The query string contains characters that a naive string comparison
    // between JavaScript's JSON.stringify() and PHP's Json::encode() would
    // not match on, such as a quote.
    // @see https://www.drupal.org/project/drupal/issues/2902769
    $query = ['foo' => '<bar>&\'"/'];
    $this->drupalGet('common-test/type-link-active-class', ['query' => $query]);
    $page = $this->getSession()->getPage();

    $special_chars_link = $page->findLink('Link with special characters in the query string');
    $this->assertTrue($special_chars_link->hasClass('is-active'));

    // Manually break the drupalSettings.path.currentQuery to prove that
    // Drupal.behaviors.activeLinks no longer relies on it: a link added to
    // the page later, with the same data-drupal-link-query attribute as the
    // one above, must still be matched correctly by reading the query from
    // window.location instead.
    $this->getSession()->executeScript('drupalSettings.path.currentQuery = {bogus: "value"};');
    $query_attribute_js = json_encode($special_chars_link->getAttribute('data-drupal-link-query'));
    $this->getSession()->executeScript(<<<JS
      var link = document.createElement('a');
      link.id = 'dynamic-active-link';
      link.textContent = 'Dynamically added link';
      link.setAttribute('data-drupal-link-system-path', 'common-test/type-link-active-class');
      link.setAttribute('data-drupal-link-query', {$query_attribute_js});
      document.body.appendChild(link);
      Drupal.attachBehaviors(document.body, drupalSettings);
      JS);

    $dynamic_link = $page->find('css', '#dynamic-active-link');
    $this->assertTrue($dynamic_link->hasClass('is-active'));
  }

  /**
   * Tests that a query string with non-Latin characters is matched.
   *
   * PHP's Json::encode() renders non-Latin text as Unicode escapes (e.g.
   * "к..."), while the browser's URL is percent-encoded UTF-8, so a
   * naive string comparison between the two never matches.
   *
   * @see https://www.drupal.org/project/drupal/issues/2525830
   */
  public function testQueryStringNonLatinCharacters(): void {
    $this->drupalLogin($this->createUser());
    $this->drupalGet('common-test/type-link-active-class', [
      'query' => ['foo' => 'комацу'],
    ]);

    $link = $this->getSession()->getPage()->findLink('Link with non-Latin characters in the query string');
    $this->assertTrue($link->hasClass('is-active'));
  }

}
