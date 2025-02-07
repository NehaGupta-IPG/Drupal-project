<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Access;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Access\RouteProcessorCsrf;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;

/**
 * @coversDefaultClass \Drupal\Core\Access\RouteProcessorCsrf
 * @group Access
 */
class RouteProcessorCsrfAjaxTest extends UnitTestCase {

  /**
   * The mock CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $csrfToken;

  /**
   * The mock request stack.
   */
  protected RequestStack&MockObject $requestStack;

  /**
   * The route processor.
   *
   * @var \Drupal\Core\Access\RouteProcessorCsrf
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->csrfToken = $this->getMockBuilder('Drupal\Core\Access\CsrfTokenGenerator')
      ->disableOriginalConstructor()
      ->getMock();

    $this->requestStack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')
      ->disableOriginalConstructor()
      ->getMock();

    $request = $this->createMock('Symfony\Component\HttpFoundation\Request');
    $request->expects($this->any())
      ->method('getRequestFormat')
      ->willReturn('html');

    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->processor = new RouteProcessorCsrf($this->csrfToken, $this->requestStack);
  }

  /**
   * Tests the processOutbound() method with CSRF token for ajax/nojs routes.
   */
  public function testProcessOutboundTokenAjax(): void {
    $route = new Route('/test-path/{js}', [], ['_csrf_token' => 'TRUE'], ['_csrf_exclude_parameters' => ['js']]);
    $parameters = ['js' => 'nojs'];

    $bubbleable_metadata = new BubbleableMetadata();
    $this->processor->processOutbound('test', $route, $parameters, $bubbleable_metadata);
    // 'token' should be added to the parameters array.
    $this->assertArrayHasKey('token', $parameters);
    // Bubbleable metadata of routes with a _csrf_ajax_token route requirement
    // is a placeholder.
    $path = 'test-path/{js}';
    $placeholder = Crypt::hashBase64($path);
    $placeholder_render_array = [
      '#lazy_builder' => ['route_processor_csrf:renderPlaceholderCsrfToken', [$path]],
    ];
    $this->assertSame($parameters['token'], $placeholder);
    $this->assertEquals((new BubbleableMetadata())->setAttachments(['placeholders' => [$placeholder => $placeholder_render_array]]), $bubbleable_metadata);
  }

}
