<?php

declare(strict_types=1);

namespace Drupal\early_rendering_controller_test;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller routines for early_rendering_test routes.
 *
 * The methods on this controller each correspond to a route for this module,
 * each of which exist solely for test cases in EarlyRenderingControllerTest;
 * see that test for documentation.
 *
 * @see core/modules/early_rendering_controller_test/early_rendering_controller_test.routing.yml
 * @see \Drupal\system\Tests\Common\EarlyRenderingControllerTest::testEarlyRendering()
 */
class EarlyRenderingTestController extends ControllerBase {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs an EarlyRenderingTestController.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer')
    );
  }

  /**
   * Returns markup with a cache tag for early rendering.
   */
  protected function earlyRenderContent() {
    return [
      '#markup' => 'Hello world!',
      '#cache' => [
        'tags' => [
          'foo',
        ],
      ],
    ];
  }

  /**
   * Returns a pre-render array.
   */
  public function renderArray() {
    return [
      '#pre_render' => [
        function () {
          $elements = $this->earlyRenderContent();
          return $elements;
        },
      ],
    ];
  }

  /**
   * Renders the early-render content and returns it as a #markup element.
   */
  public function renderArrayEarly() {
    $render_array = $this->earlyRenderContent();
    return [
      '#markup' => $this->renderer->render($render_array),
    ];
  }

  /**
   * Returns an Ajax response with the content rendered.
   */
  public function ajaxResponse() {
    $response = new AjaxResponse();
    $response->addCommand(new InsertCommand(NULL, $this->renderArray()));
    return $response;
  }

  /**
   * Returns an Ajax response with the content rendered early.
   */
  public function ajaxResponseEarly() {
    $response = new AjaxResponse();
    $response->addCommand(new InsertCommand(NULL, $this->renderArrayEarly()));
    return $response;
  }

  /**
   * Returns a simple 'Hello world!' response.
   */
  public function response() {
    return new Response('Hello world!');
  }

  /**
   * Renders the content, casts it to a string, and returns it as a response.
   */
  public function responseEarly() {
    $render_array = $this->earlyRenderContent();
    return new Response((string) $this->renderer->render($render_array));
  }

  /**
   * Returns a response with an attachment.
   */
  public function responseWithAttachments() {
    return new AttachmentsTestResponse('Hello world!');
  }

  /**
   * Returns a response with the early-rendered content and an attachment.
   */
  public function responseWithAttachmentsEarly() {
    $render_array = $this->earlyRenderContent();
    return new AttachmentsTestResponse((string) $this->renderer->render($render_array));
  }

  /**
   * Returns a cacheable "Hello world" response.
   */
  public function cacheableResponse() {
    return new CacheableTestResponse('Hello world!');
  }

  /**
   * Returns a cacheable response with the content rendered early.
   */
  public function cacheableResponseEarly() {
    $render_array = $this->earlyRenderContent();
    return new CacheableTestResponse((string) $this->renderer->render($render_array));
  }

  /**
   * Returns the test domain object.
   */
  public function domainObject() {
    return new TestDomainObject();
  }

  /**
   * Returns the test domain object with the content rendered early.
   */
  public function domainObjectEarly() {
    $render_array = $this->earlyRenderContent();
    $this->renderer->render($render_array);
    return new TestDomainObject();
  }

  /**
   * Returns the test domain object with an attachment.
   */
  public function domainObjectWithAttachments() {
    return new AttachmentsTestDomainObject();
  }

  /**
   * Returns the test object with early rendering content and an attachment.
   */
  public function domainObjectWithAttachmentsEarly() {
    $render_array = $this->earlyRenderContent();
    $this->renderer->render($render_array);
    return new AttachmentsTestDomainObject();
  }

  /**
   * Returns a cacheable test domain object.
   */
  public function cacheableDomainObject() {
    return new CacheableTestDomainObject();
  }

  /**
   * Returns a cacheable test domain object with early rendering content.
   */
  public function cacheableDomainObjectEarly() {
    $render_array = $this->earlyRenderContent();
    $this->renderer->render($render_array);
    return new CacheableTestDomainObject();
  }

}
