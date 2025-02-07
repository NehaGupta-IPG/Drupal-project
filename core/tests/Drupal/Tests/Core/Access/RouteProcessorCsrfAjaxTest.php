<?php

namespace Drupal\Tests\Core\Access;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Access\RouteProcessorCsrf;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Tests\UnitTestCase;
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

    $this->processor = new RouteProcessorCsrf($this->csrfToken);
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
