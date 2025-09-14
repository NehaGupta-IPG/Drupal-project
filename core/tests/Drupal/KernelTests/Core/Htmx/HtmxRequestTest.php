<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Htmx;

use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
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
   * Test get method.
   */
  public function testHxGet(): void {
    $this->htmx->get($this->url);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-get']));
    $expected = '/htmx-test-attachments/page?' . MainContentViewSubscriber::WRAPPER_FORMAT . '=drupal_htmx';
    // The paths in GitLabCI include a subfolder.
    $this->assertStringEndsWith($expected, $render['#attributes']['data-hx-get']);
    // Verify no wrapper format.
    $this->htmx->get($this->url, TRUE);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-get']));
    $this->assertStringEndsWith('/htmx-test-attachments/page', $render['#attributes']['data-hx-get']);
    // Verify no parameters.
    $url = Url::fromRoute('test_htmx.attachments.replace');
    $request = Request::create($url->toString());
    $this->httpKernel->handle($request);
    $this->htmx->get();
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-get']));
    $expected = '/htmx-test-attachments/replace?' . MainContentViewSubscriber::WRAPPER_FORMAT . '=drupal_htmx';
    $this->assertStringEndsWith($expected, $render['#attributes']['data-hx-get']);
    // Verify no parameters with no wrapper format.
    $this->htmx->get(NULL, TRUE);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-get']));
    $this->assertStringEndsWith('/htmx-test-attachments/replace', $render['#attributes']['data-hx-get']);
  }

  /**
   * Test post method.
   */
  public function testHxPost(): void {
    $this->htmx->post($this->url);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-post']));
    $expected = '/htmx-test-attachments/page?' . MainContentViewSubscriber::WRAPPER_FORMAT . '=drupal_htmx';
    // The paths in GitLabCI include a subfolder.
    $this->assertStringEndsWith($expected, $render['#attributes']['data-hx-post']);
    // Verify no wrapper format.
    $this->htmx->post($this->url, TRUE);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-post']));
    $this->assertStringEndsWith('/htmx-test-attachments/page', $render['#attributes']['data-hx-post']);
    // Verify no parameters.
    $url = Url::fromRoute('test_htmx.attachments.replace');
    $request = Request::create($url->toString());
    $this->httpKernel->handle($request);
    $this->htmx->post();
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-post']));
    $expected = '/htmx-test-attachments/replace?' . MainContentViewSubscriber::WRAPPER_FORMAT . '=drupal_htmx';
    $this->assertStringEndsWith($expected, $render['#attributes']['data-hx-post']);
    // Verify no parameters with no wrapper format.
    $this->htmx->post(NULL, TRUE);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-post']));
    $this->assertStringEndsWith('/htmx-test-attachments/replace', $render['#attributes']['data-hx-post']);
  }

  /**
   * Test put method.
   */
  public function testHxPut(): void {
    $this->htmx->put($this->url);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-put']));
    $expected = '/htmx-test-attachments/page?' . MainContentViewSubscriber::WRAPPER_FORMAT . '=drupal_htmx';
    // The paths in GitLabCI include a subfolder.
    $this->assertStringEndsWith($expected, $render['#attributes']['data-hx-put']);
    // Verify no wrapper format.
    $this->htmx->put($this->url, TRUE);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-put']));
    $this->assertStringEndsWith('/htmx-test-attachments/page', $render['#attributes']['data-hx-put']);
    // Verify no parameters.
    $url = Url::fromRoute('test_htmx.attachments.replace');
    $request = Request::create($url->toString());
    $this->httpKernel->handle($request);
    $this->htmx->put();
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-put']));
    $expected = '/htmx-test-attachments/replace?' . MainContentViewSubscriber::WRAPPER_FORMAT . '=drupal_htmx';
    $this->assertStringEndsWith($expected, $render['#attributes']['data-hx-put']);
    // Verify no parameters with no wrapper format.
    $this->htmx->put(NULL, TRUE);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-put']));
    $this->assertStringEndsWith('/htmx-test-attachments/replace', $render['#attributes']['data-hx-put']);
  }

  /**
   * Test patch method.
   */
  public function testHxPatch(): void {
    $this->htmx->patch($this->url);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-patch']));
    $expected = '/htmx-test-attachments/page?' . MainContentViewSubscriber::WRAPPER_FORMAT . '=drupal_htmx';
    // The paths in GitLabCI include a subfolder.
    $this->assertStringEndsWith($expected, $render['#attributes']['data-hx-patch']);
    // Verify no wrapper format.
    $this->htmx->patch($this->url, TRUE);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-patch']));
    $this->assertStringEndsWith('/htmx-test-attachments/page', $render['#attributes']['data-hx-patch']);
    // Verify no parameters.
    $url = Url::fromRoute('test_htmx.attachments.replace');
    $request = Request::create($url->toString());
    $this->httpKernel->handle($request);
    $this->htmx->patch();
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-patch']));
    $expected = '/htmx-test-attachments/replace?' . MainContentViewSubscriber::WRAPPER_FORMAT . '=drupal_htmx';
    $this->assertStringEndsWith($expected, $render['#attributes']['data-hx-patch']);
    // Verify no parameters with no wrapper format.
    $this->htmx->patch(NULL, TRUE);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-patch']));
    $this->assertStringEndsWith('/htmx-test-attachments/replace', $render['#attributes']['data-hx-patch']);
  }

  /**
   * Test delete method.
   */
  public function testHxDelete(): void {
    $this->htmx->delete($this->url);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-delete']));
    $expected = '/htmx-test-attachments/page?' . MainContentViewSubscriber::WRAPPER_FORMAT . '=drupal_htmx';
    // The paths in GitLabCI include a subfolder.
    $this->assertStringEndsWith($expected, $render['#attributes']['data-hx-delete']);
    // Verify no wrapper format.
    $this->htmx->delete($this->url, TRUE);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-delete']));
    $this->assertStringEndsWith('/htmx-test-attachments/page', $render['#attributes']['data-hx-delete']);
    // Verify no parameters.
    $url = Url::fromRoute('test_htmx.attachments.replace');
    $request = Request::create($url->toString());
    $this->httpKernel->handle($request);
    $this->htmx->delete();
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-delete']));
    $expected = '/htmx-test-attachments/replace?' . MainContentViewSubscriber::WRAPPER_FORMAT . '=drupal_htmx';
    $this->assertStringEndsWith($expected, $render['#attributes']['data-hx-delete']);
    // Verify no parameters with no wrapper format.
    $this->htmx->delete(NULL, TRUE);
    $render = $this->apply();
    $this->assertTrue(isset($render['#attributes']['data-hx-delete']));
    $this->assertStringEndsWith('/htmx-test-attachments/replace', $render['#attributes']['data-hx-delete']);
  }

}
