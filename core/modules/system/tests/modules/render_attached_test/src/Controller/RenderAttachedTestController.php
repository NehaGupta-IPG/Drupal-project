<?php

declare(strict_types=1);

namespace Drupal\render_attached_test\Controller;

use Drupal\Core\Routing\Attribute\Route;

/**
 * Controller for various permutations of #attached in the render array.
 */
class RenderAttachedTestController {

  /**
   * Tests special header and status code rendering.
   *
   * @return array
   *   A render array using features of the 'http_header' directive.
   */
  #[Route(
    path: '/render_attached_test/teapot',
    name: 'render_attached.teapot',
    requirements: ['_access' => 'TRUE'],
  )]
  public function teapotHeaderStatus() {
    $render = [];
    $render['#attached']['http_header'][] = ['Status', 418];
    return $render;
  }

  /**
   * Tests attached HTML head rendering.
   *
   * @return array
   *   A render array using the 'http_head' directive.
   */
  #[Route(
    path: '/render_attached_test/header',
    name: 'render_attached.header',
    requirements: ['_access' => 'TRUE'],
  )]
  public function header() {
    $render = [];
    $render['#attached']['http_header'][] = ['X-Test-Teapot-Replace', 'This value gets replaced'];
    $render['#attached']['http_header'][] = ['X-Test-Teapot-Replace', 'Teapot replaced', TRUE];
    $render['#attached']['http_header'][] = ['X-Test-Teapot-No-Replace', 'This value is not replaced'];
    $render['#attached']['http_header'][] = ['X-Test-Teapot-No-Replace', 'This one is added', FALSE];
    $render['#attached']['http_header'][] = ['X-Test-Teapot', 'Teapot Mode Active'];
    return $render;
  }

  /**
   * Tests attached HTML head rendering.
   *
   * @return array
   *   A render array using the 'html_head' directive.
   */
  #[Route(
    path: '/render_attached_test/head',
    name: 'render_attached.head',
    requirements: ['_access' => 'TRUE'],
  )]
  public function head() {
    $head = [
      [
        '#tag' => 'meta',
        '#attributes' => [
          'test-attribute' => 'test_value',
        ],
      ],
      'test_head_attribute',
    ];

    $render = [];
    $render['#attached']['html_head'][] = $head;
    return $render;
  }

  /**
   * Tests attached feed rendering.
   *
   * @return array
   *   A render array using the 'feed' directive.
   */
  #[Route(
    path: '/render_attached_test/feed',
    name: 'render_attached.feed_single',
    requirements: ['_access' => 'TRUE'],
  )]
  public function feed() {
    $render = [];
    $render['#attached']['feed'][] = ['test://url', 'Your RSS feed.'];
    return $render;
  }

  /**
   * Tests HTTP header rendering for link.
   *
   * @return array
   *   A render array using the 'html_head_link' directive.
   */
  #[Route(
    path: '/render_attached_test/html_header_link',
    name: 'render_attached.html_header_link',
    requirements: ['_access' => 'TRUE'],
  )]
  public function htmlHeaderLink() {
    $render = [];
    $render['#attached']['html_head_link'][] = [['href' => '/foo?bar=<baz>&baz=false', 'rel' => 'alternate'], TRUE];
    $render['#attached']['html_head_link'][] = [['href' => '/not-added-to-http-headers', 'rel' => 'alternate'], FALSE];
    $render['#attached']['html_head_link'][] = [['href' => '/foo/bar', 'hreflang' => 'nl', 'rel' => 'alternate'], TRUE];
    $render['#attached']['html_head_link'][] = [['href' => '/foo/bar', 'hreflang' => 'de', 'rel' => 'alternate'], TRUE];
    return $render;
  }

}
