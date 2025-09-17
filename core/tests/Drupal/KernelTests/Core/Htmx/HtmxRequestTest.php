<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Htmx;

use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Test the request attributes for HTMX.
 */
#[CoversClass(Htmx::class)]
#[Group('Htmx')]
class HtmxRequestTest extends KernelTestBase {

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
    $this->installConfig(['system']);
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
   * Test all 5 request verb methods.
   */
  #[DataProvider('htmxRequestProvider')]
  public function testHxRequest(string $method): void {
    $attribute = "data-hx-$method";
    $this->htmx->$method($this->url);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes'][$attribute]));
    $expected = '/htmx-test-attachments/page?' . MainContentViewSubscriber::WRAPPER_FORMAT . '=drupal_htmx';
    // The paths in GitLabCI include a subfolder.
    $this->assertStringEndsWith($expected, $render['#attributes'][$attribute]);
    // Verify no wrapper format.
    $this->htmx->$method($this->url, TRUE);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes'][$attribute]));
    $this->assertStringEndsWith('/htmx-test-attachments/page', $render['#attributes'][$attribute]);
    // Verify no parameters.
    $url = Url::fromRoute('test_htmx.attachments.replace');
    $request = Request::create($url->toString());
    $this->httpKernel->handle($request);
    $this->htmx->$method();
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes'][$attribute]));
    $expected = '?' . MainContentViewSubscriber::WRAPPER_FORMAT . '=drupal_htmx';
    $this->assertTrue($expected === $render['#attributes'][$attribute]);
    // Verify no parameters with no wrapper format.
    $this->htmx->$method(full_page: TRUE);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes'][$attribute]));
    $this->assertTrue('' === $render['#attributes'][$attribute]);
  }

  /**
   * Data provider for testHxRequest.
   */
  public static function htmxRequestProvider(): array {
    return [
      ['get'],
      ['post'],
      ['put'],
      ['patch'],
      ['delete'],
    ];
  }

}
