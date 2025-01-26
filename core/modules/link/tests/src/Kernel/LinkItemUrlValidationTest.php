<?php

declare(strict_types=1);

namespace Drupal\Tests\link\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\link\LinkItemInterface;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;

/**
 * Tests link field validation.
 *
 * @group link
 */
class LinkItemUrlValidationTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['link'];

  /**
   * Entity with no access.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $forbiddenEntity;

  /**
   * Entity with no access.
   *
   * @var \Drupal\link\LinkItemInterface
   */
  protected LinkItemInterface $linkItem;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $definition = \Drupal::typedDataManager()
      ->createDataDefinition('field_item:link');
    $this->linkItem = \Drupal::typedDataManager()->create($definition);
  }

  /**
   * Tests external link validation.
   */
  public function testExternalLinkValidation(): void {
    $this->checkViolations($this->getExternalTestLinks());
  }

  /**
   * Tests internal link validation.
   */
  public function testInternalLinkValidation(): void {
    $this->forbiddenEntity = EntityTest::create(['name' => 'forbid_access']);
    $this->forbiddenEntity->save();

    $this->checkViolations($this->getInternalTestLinks());
  }

  /**
   * Helper method to validate url.
   *
   * @param array $test_links
   *   The first element of the array is the link value to test. The second
   *   value is an array of expected violation messages.
   */
  protected function checkViolations(array $test_links): void {
    foreach ($test_links as $data) {
      [$value, $expected_violations] = $data;
      $this->linkItem->setValue($value);
      $violations = $this->linkItem->validate();

      $i = 0;
      foreach ($violations as $violation) {
        $this->assertTrue(isset($expected_violations[$i]), 'Unexpected violation: ' . $violation->getMessage());
        $error_msg = $expected_violations[$i++];
        // If the expected message contains a '%' add the current link value.
        if (strpos($error_msg, '%')) {
          $error_msg = sprintf($error_msg, $value);
        }
        $this->assertEquals($error_msg, $violation->getMessage());
      }

      $expected_count = count($expected_violations);
      $this->assertCount($expected_count, $violations, sprintf('Violation message count error for %s', $value));
    }
  }

  /**
   * Builds an array of external links to test.
   *
   * @return array
   *   The first element of the array is the link value to test. The second
   *   value is an array of expected violation messages.
   */
  protected function getExternalTestLinks(): array {
    $violation_0 = "The path '%s' is invalid.";
    $violation_1 = 'This value should be of the correct primitive type.';
    return [
      ['invalid://not-a-valid-protocol', [$violation_0]],
      ['http://www.example.com/', []],
      // Strings within parenthesis without leading space char.
      ['http://www.example.com/strings_(string_within_parenthesis)', []],
      // Numbers within parenthesis without leading space char.
      ['http://www.example.com/numbers_(9999)', []],
      ['http://www.example.com/?name=ferret&color=purple', []],
      ['http://www.example.com/page?name=ferret&color=purple', []],
      ['http://www.example.com?a=&b[]=c&d[]=e&d[]=f&h==', []],
      ['http://www.example.com#colors', []],
      // Use list of valid URLS from],
      // https://cran.r-project.org/web/packages/rex/vignettes/url_parsing.html.
      ["http://foo.com/blah_blah", []],
      ["http://foo.com/blah_blah/", []],
      ["http://foo.com/blah_blah_(wikipedia)", []],
      ["http://foo.com/blah_blah_(wikipedia)_(again)", []],
      ["http://www.example.com/wpstyle/?p=364", []],
      ["https://www.example.com/foo/?bar=baz&inga=42&quux", []],
      ["http://✪df.ws/123", []],
      ["http://userid:password@example.com:8080", []],
      ["http://userid:password@example.com:8080/", []],
      ["http://userid@example.com", []],
      ["http://userid@example.com/", []],
      ["http://userid@example.com:8080", []],
      ["http://userid@example.com:8080/", []],
      ["http://userid:password@example.com", []],
      ["http://userid:password@example.com/", []],
      ["http://➡.ws/䨹", []],
      ["http://⌘.ws", []],
      ["http://⌘.ws/", []],
      ["http://foo.com/blah_(wikipedia)#cite-1", []],
      ["http://foo.com/blah_(wikipedia)_blah#cite-1", []],
      // The following invalid URLs produce false positives.
      ["http://foo.com/unicode_(✪)_in_parens", []],
      ["http://foo.com/(something)?after=parens", []],
      ["http://☺.damowmow.com/", []],
      ["http://code.google.com/events/#&product=browser", []],
      ["http://j.mp", []],
      ["ftp://foo.bar/baz", []],
      ["http://foo.bar/?q=Test%20URL-encoded%20stuff", []],
      ["http://مثال.إختبار", []],
      ["http://例子.测试", []],
      ["http://-.~_!$&'()*+,;=:%40:80%2f::::::@example.com", []],
      ["http://1337.net", []],
      ["http://a.b-c.de", []],
      ["radar://1234", [$violation_0]],
      ["h://test", [$violation_0]],
      ["ftps://foo.bar/", [$violation_0]],
      // Use invalid URLS from
      // https://cran.r-project.org/web/packages/rex/vignettes/url_parsing.html.
      ['http://', [$violation_0, $violation_1]],
      ["http://?", [$violation_0, $violation_1]],
      ["http://??", [$violation_0, $violation_1]],
      ["http://??/", [$violation_0, $violation_1]],
      ["http://#", [$violation_0, $violation_1]],
      ["http://##", [$violation_0, $violation_1]],
      ["http://##/", [$violation_0, $violation_1]],
      ["//", [$violation_0, $violation_1]],
      ["///a", [$violation_0, $violation_1]],
      ["///", [$violation_0, $violation_1]],
      ["http:///a", [$violation_0, $violation_1]],
    ];
  }

  /**
   * Builds an array of internal links to test.
   *
   * @return array
   *   The first element of the array is the link value to test. The second
   *   value is an array of expected violation messages.
   */
  protected function getInternalTestLinks(): array {
    $violation_0 = "The path '%s' is inaccessible.";
    return [
      // Text-only links.
      ['route:<nolink>', []],
      ['route:<button>', []],
      ['route:<button>', []],

      // Query string and fragment.
      ['internal:?example=llama', []],
      ['internal:#example', []],

      // Complex query string. Similar to facet links.
      ['internal:?a[]=1&a[]=2', []],
      ['internal:?b[0]=1&b[1]=2', []],
      ['internal:?e[f][g]=h', []],
      ['internal:?i[j[k]]=l', []],
      ['internal:?x=1&x=2', []],
      ['internal:?z[0]=1&z[0]=2', []],

      // URI for an entity that doesn't exist, but with a valid ID.
      ['entity:user/99999', [$violation_0]],
      // URI for an entity that exists, but is not accessible.
      ['entity:entity_test/' . $this->forbiddenEntity->id(), [$violation_0]],

    ];
  }

}
