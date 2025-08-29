<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Htmx;

use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Render\MainContent\HtmxRenderer;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Verifies HtmxRenderer.
 */
#[CoversClass(HtmxRenderer::class)]
#[Group('Htmx')]
class HtmxRendererTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'test_htmx',
  ];

  /**
   * Injected kernel service.
   */
  protected HttpKernelInterface $httpKernel;

  /**
   * Test triggering the renderer with _wrapper_format.
   */
  public function testWrapperFormat(): void {
    $options = [
      'query' => [
        MainContentViewSubscriber::WRAPPER_FORMAT => 'drupal_htmx',
      ],
    ];
    $url = Url::fromRoute('test_htmx.attachments.replace', [], $options);
    $request = Request::create($url->toString());
    $response = $this->httpKernel->handle($request);
    $this->verifyResponse($response);
  }

  /**
   * Each test method returns the same markup.
   *
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The response under test.
   */
  protected function verifyResponse(Response $response): void {
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
    $oneLine = str_replace(["\r", "\n"], "", $response->getContent());
    $this->assertStringContainsString('<body><div class="ajax-content">Initial Content</div></body>', $oneLine);
  }

  /**
   * Test triggering the renderer with the _htmx_route option.
   */
  public function testHtmxRoute(): void {
    $url = Url::fromRoute('test_htmx.attachments.htmx_route');
    $request = Request::create($url->toString());
    $response = $this->httpKernel->handle($request);
    $this->verifyResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->setCurrentUser($this->createUser([
      'access content',
    ]));
    $this->httpKernel = $this->container->get('http_kernel');
  }

}
