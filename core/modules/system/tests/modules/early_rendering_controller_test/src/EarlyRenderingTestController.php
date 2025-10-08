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
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('renderer')
    );
  }

  protected function earlyRenderContent(): array {
    return [
      '#markup' => 'Hello world!',
      '#cache' => [
        'tags' => [
          'foo',
        ],
      ],
    ];
  }

  public function renderArray(): array {
    return [
      '#pre_render' => [
        function () {
          $elements = $this->earlyRenderContent();
          return $elements;
        },
      ],
    ];
  }

  public function renderArrayEarly(): array {
    $render_array = $this->earlyRenderContent();
    return [
      '#markup' => $this->renderer->render($render_array),
    ];
  }

  public function ajaxResponse(): \Drupal\Core\Ajax\AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new InsertCommand(NULL, $this->renderArray()));
    return $response;
  }

  public function ajaxResponseEarly(): \Drupal\Core\Ajax\AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new InsertCommand(NULL, $this->renderArrayEarly()));
    return $response;
  }

  public function response(): \Symfony\Component\HttpFoundation\Response {
    return new Response('Hello world!');
  }

  public function responseEarly(): \Symfony\Component\HttpFoundation\Response {
    $render_array = $this->earlyRenderContent();
    return new Response((string) $this->renderer->render($render_array));
  }

  public function responseWithAttachments(): \Drupal\early_rendering_controller_test\AttachmentsTestResponse {
    return new AttachmentsTestResponse('Hello world!');
  }

  public function responseWithAttachmentsEarly(): \Drupal\early_rendering_controller_test\AttachmentsTestResponse {
    $render_array = $this->earlyRenderContent();
    return new AttachmentsTestResponse((string) $this->renderer->render($render_array));
  }

  public function cacheableResponse(): \Drupal\early_rendering_controller_test\CacheableTestResponse {
    return new CacheableTestResponse('Hello world!');
  }

  public function cacheableResponseEarly(): \Drupal\early_rendering_controller_test\CacheableTestResponse {
    $render_array = $this->earlyRenderContent();
    return new CacheableTestResponse((string) $this->renderer->render($render_array));
  }

  public function domainObject(): \Drupal\early_rendering_controller_test\TestDomainObject {
    return new TestDomainObject();
  }

  public function domainObjectEarly(): \Drupal\early_rendering_controller_test\TestDomainObject {
    $render_array = $this->earlyRenderContent();
    $this->renderer->render($render_array);
    return new TestDomainObject();
  }

  public function domainObjectWithAttachments(): \Drupal\early_rendering_controller_test\AttachmentsTestDomainObject {
    return new AttachmentsTestDomainObject();
  }

  public function domainObjectWithAttachmentsEarly(): \Drupal\early_rendering_controller_test\AttachmentsTestDomainObject {
    $render_array = $this->earlyRenderContent();
    $this->renderer->render($render_array);
    return new AttachmentsTestDomainObject();
  }

  public function cacheableDomainObject(): \Drupal\early_rendering_controller_test\CacheableTestDomainObject {
    return new CacheableTestDomainObject();
  }

  public function cacheableDomainObjectEarly(): \Drupal\early_rendering_controller_test\CacheableTestDomainObject {
    $render_array = $this->earlyRenderContent();
    $this->renderer->render($render_array);
    return new CacheableTestDomainObject();
  }

}
