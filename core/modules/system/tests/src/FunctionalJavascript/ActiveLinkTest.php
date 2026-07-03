<?php

declare(strict_types=1);

namespace Drupal\Tests\system\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

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
   * Tests subset query matching for authenticated users (client-side marking).
   */
  public function testSubsetQueryMatching(): void {
    $user = $this->createUser();
    $this->drupalLogin($user);

    $path = 'common-test/type-link-active-class';
    $this->drupalGet($path, ['query' => ['page' => '3']]);

    $no_query_href = Url::fromRoute('common_test.l_active_class', [], [])->toString();
    $this->assertSession()->elementExists('xpath', $this->assertSession()->buildXPathQuery(
      '//a[@href = :href and contains(@class, "is-active")]',
      [':href' => $no_query_href],
    ));

    $this->drupalGet($path, ['query' => ['page' => '3', 'foo' => 'bar', 'one' => 'two']]);
    $partial_query_href = Url::fromRoute('common_test.l_active_class', [], [
      'query' => ['foo' => 'bar', 'one' => 'two'],
    ])->toString();
    $this->assertSession()->elementExists('xpath', $this->assertSession()->buildXPathQuery(
      '//a[@href = :href and contains(@class, "is-active")]',
      [':href' => $partial_query_href],
    ));

    $other_query_href = Url::fromRoute('common_test.l_active_class', [], [
      'query' => ['bar' => 'baz', 'three' => 'four'],
    ])->toString();
    $this->assertSession()->elementExists('xpath', $this->assertSession()->buildXPathQuery(
      '//a[@href = :href and not(contains(@class, "is-active"))]',
      [':href' => $other_query_href],
    ));
  }

  /**
   * Tests that "0" and empty-string query values match in JavaScript.
   */
  public function testZeroAndEmptyQueryValues(): void {
    $user = $this->createUser();
    $this->drupalLogin($user);

    $this->drupalGet('common-test/type-link-active-class', ['query' => ['count' => '0']]);
    $this->assertSession()->elementExists('xpath', $this->assertSession()->buildXPathQuery(
      '//a[@href = :href and contains(@class, "is-active")]',
      [
        ':href' => Url::fromRoute('common_test.l_active_class', [], [
          'query' => ['count' => '0'],
        ])->toString(),
      ],
    ));

    $this->drupalGet('common-test/type-link-active-class', ['query' => ['status' => '']]);
    $this->assertSession()->elementExists('xpath', $this->assertSession()->buildXPathQuery(
      '//a[@href = :href and contains(@class, "is-active")]',
      [
        ':href' => Url::fromRoute('common_test.l_active_class', [], [
          'query' => ['status' => ''],
        ])->toString(),
      ],
    ));
  }

}
