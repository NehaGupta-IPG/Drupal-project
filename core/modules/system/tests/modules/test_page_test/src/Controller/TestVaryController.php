<?php

namespace Drupal\test_page_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\EventSubscriber\VaryHeaderResponseSubscriber;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Page controller that is aware of Vary header.
 *
 * @internal
 */
class TestVaryController extends ControllerBase {

  /**
   * The vary policy.
   *
   * @var \Drupal\Core\EventSubscriber\VaryHeaderResponseSubscriber
   */
  protected $varyResponseSubscriber;

  /**
   * Constructs the TestVaryController object.
   *
   * @param \Drupal\Core\EventSubscriber\VaryHeaderResponseSubscriber $varyResponseSubscriber
   *   The vary policy.
   */
  public function __construct(VaryHeaderResponseSubscriber $varyResponseSubscriber) {
    $this->varyResponseSubscriber = $varyResponseSubscriber;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('vary_header_response_subscriber')
    );
  }

  /**
   * Page that displays content that depends on custom request header.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   Renderable array expected by renderer service.
   */
  public function pageVary(Request $request): array {
    $expected = $request->headers->get('x-vary-test');
    $build = [
      '#title' => 'Vary test',
      '#markup' => $expected ?: 'default header value',
    ];
    $this->varyResponseSubscriber->addVaryHeader('X-Vary-Test');
    return $build;
  }

}
