<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Functional;

use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl.
 */
#[CoversClass(LanguageNegotiationUrl::class)]
#[Group('language')]
#[RunTestsInSeparateProcesses]
class LanguageNegotiationUrlTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'language_test',
    'node',
    'path',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an Article node type.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(['type' => 'article']);
    }

    $this->user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'view the administration theme',
      'administer nodes',
      'create article content',
      'create url aliases',
    ]);
    $this->drupalLogin($this->user);

    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm(['predefined_langcode' => 'de'], 'Add language');
  }

  /**
   * Tests domain.
   *
   * @legacy-covers ::processInbound
   */
  public function testDomain(): void {
    // Check if paths that contain language prefixes can be reached when
    // language is taken from the domain.
    $edit = [
      'language_negotiation_url_part' => 'domain',
      'prefix[en]' => 'eng',
      'prefix[de]' => 'de',
      'domain[en]' => $_SERVER['HTTP_HOST'],
      'domain[de]' => "de.$_SERVER[HTTP_HOST]",
    ];
    $this->drupalGet('admin/config/regional/language/detection/url');
    $this->submitForm($edit, 'Save configuration');

    $nodeValues = [
      'title[0][value]' => 'Test',
      'path[0][alias]' => '/eng/test',
    ];
    $this->drupalGet('node/add/article');
    $this->submitForm($nodeValues, 'Save');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Test local domain redirects.
   *
   * @covers \Drupal\Component\Utility\UrlHelper::externalIsTrustedLocal
   */
  public function testLocalDomainRedirect(): void {
    global $base_url, $base_path;

    // Get the current host URI we're running on.
    $base_url_host = parse_url($base_url, PHP_URL_HOST);
    $base_scheme = parse_url($base_url, PHP_URL_SCHEME);

    // Disable automatic following of redirects by the HTTP client, so that this
    // test can analyze the response headers of each redirect response.
    $this->followRedirects(FALSE);

    // Ensure that the redirects are not allowed when not using a
    // domain-based redirect.
    $edit = [
      'language_negotiation_url_part' => 'path_prefix',
      'prefix[en]' => 'eng',
      'prefix[de]' => 'de',
      'domain[en]' => $base_url_host,
      'domain[de]' => 'example.de',
    ];
    $this->drupalGet('admin/config/regional/language/detection/url');
    $this->submitForm($edit, 'Save configuration');

    $this->drupalGet('/language_test/redirect/en');
    $this->assertSession()->statusCodeEquals(302);
    $this->assertEquals($base_scheme . '://' . $base_url_host . $base_path, $this->getSession()->getResponseHeaders()['Location'][0]);
    $this->drupalGet('/language_test/redirect/de');
    $this->assertSession()->statusCodeEquals(400);
    $this->drupalGet('/language_test/redirect/unsupported');
    $this->assertSession()->statusCodeEquals(400);

    // Ensure that the domain URLs can be suitable local redirect target URLs.
    $edit = [
      'language_negotiation_url_part' => 'domain',
    ];
    $this->drupalGet('admin/config/regional/language/detection/url');
    $this->submitForm($edit, 'Save configuration');

    $this->drupalGet('/language_test/redirect/en');
    $this->assertSession()->statusCodeEquals(302);
    $this->assertStringContainsString($base_scheme . '://' . $base_url_host . $base_path, $this->getSession()->getResponseHeaders()['Location'][0]);
    $this->drupalGet('/language_test/redirect/de');
    $this->assertSession()->statusCodeEquals(302);
    $this->assertStringContainsString($base_scheme . '://example.de' . $base_path, $this->getSession()->getResponseHeaders()['Location'][0]);
    $this->drupalGet('/language_test/redirect/unsupported');
    $this->assertSession()->statusCodeEquals(400);
    $this->assertSession()->responseContains('Redirects to external URLs are not allowed by default');
  }

  /**
   * Whether to follow redirects.
   */
  protected function followRedirects(bool $follow_redirects): void {
    $this->getSession()->getDriver()->getClient()->followRedirects($follow_redirects);
    $this->maximumMetaRefreshCount = $follow_redirects ? NULL : 0;
  }

}
