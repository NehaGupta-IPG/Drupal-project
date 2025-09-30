<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Core\Htmx;

use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Test the request attributes for HTMX.
 */
#[CoversClass(Htmx::class)]
#[Group('Htmx')]
class HtmxRequestTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'test_htmx',
  ];

  /**
   * Class under test.
   */
  protected Htmx $htmx;

  /**
   * Injected kernel service.
   */
  protected HttpKernelInterface $httpKernel;

  /**
   * Prepared Url object.
   */
  protected Url $url;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->htmx = new Htmx();
    $this->httpKernel = $this->container->get('http_kernel');
    $this->url = Url::fromRoute('test_htmx.attachments.page');
  }

  /**
   * Applies the Htmx attributes to a render array.
   */
  protected function apply(): array {
    $render = [];
    $this->htmx->applyTo($render);
    return $render;
  }

  /**
   * Test all 5 request verb methods with and without wrapper format.
   */
  public function testHxRequest(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
    ]));

    $this->drupalGet('/htmx-test/ajax-request-wrapper-format');
    $page = $this->getSession()->getPage();

    foreach (['get', 'post', 'put', 'delete', 'patch'] as $method) {
      $page->findById('button-drupal-htmx-' . $method)->click();
      $rendered = $this->assertSession()->waitForText('Hello, world Htmx drupal-htmx ' . $method);
      $this->assertTrue($rendered, 'Did not get expected text from HTMX request.');

      $el = $page->findById('wrapper-htmx-response-drupal-htmx-' . $method);
      $this->assertNotNull($el);
      $this->assertTrue($el->getAttribute('_wrapper_format') === 'drupal_htmx');

      // Test full page.
      $page->findById('button-full-page-' . $method)->click();
      $rendered = $this->assertSession()->waitForText('Hello, world Htmx full-page ' . $method);
      $this->assertTrue($rendered, 'Did not get expected text from HTMX request.');

      $el = $page->findById('wrapper-htmx-response-full-page-' . $method);
      $this->assertNotNull($el);
      $this->assertNull($el->getAttribute('_wrapper_format'));
    }

  }

}
