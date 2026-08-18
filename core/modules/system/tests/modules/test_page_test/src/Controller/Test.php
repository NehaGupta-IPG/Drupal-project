<?php

declare(strict_types=1);

namespace Drupal\test_page_test\Controller;

use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\Attribute\Route;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Defines a test controller for page titles.
 */
class Test {

  /**
   * Renders a page with a title.
   *
   * @return array
   *   A render array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  #[Route(
    path: '/test-render-title',
    name: 'test_page_test.render_title',
    requirements: ['_access' => 'TRUE'],
  )]
  public function renderTitle() {
    $build = [];
    $build['#markup'] = 'Hello Drupal';
    $build['#title'] = 'Foo';

    return $build;
  }

  /**
   * Renders a page.
   *
   * @return array
   *   A render array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  #[Route(
    path: '/test-page-static-title',
    name: 'test_page_test.static_title',
    title: new TranslatableMarkup('Static title'),
    requirements: ['_access' => 'TRUE'],
  )]
  #[Route(
    path: '/test-page-dynamic-title',
    name: 'test_page_test.dynamic_title',
    requirements: ['_access' => 'TRUE'],
    defaults: ['_title_callback' => '\\Drupal\\test_page_test\\Controller\\Test::dynamicTitle'],
  )]
  public function staticTitle() {
    $build = [];
    $build['#markup'] = 'Hello Drupal';

    return $build;
  }

  /**
   * Returns a 'dynamic' title for the '_title_callback' route option.
   *
   * @return string
   *   The page title.
   */
  public function dynamicTitle() {
    return 'Dynamic title';
  }

  /**
   * Defines a controller with a cached render array.
   *
   * @return array
   *   A render array
   */
  #[Route(
    path: '/test-page-cached-controller',
    name: 'test_page_test.cached_controller',
    requirements: ['_access' => 'TRUE'],
  )]
  public function controllerWithCache() {
    $build = [];
    $build['#title'] = '<span>Cached title</span>';
    $build['#cache']['keys'] = ['test_controller', 'with_title'];

    return $build;
  }

  /**
   * Returns a generic page render array for title tests.
   *
   * @return array
   *   A render array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  public function renderPage() {
    return [
      '#markup' => 'Content',
    ];
  }

  /**
   * Throws a HTTP exception.
   *
   * @param int $code
   *   The status code.
   */
  #[Route(
    path: '/test-http-response-exception/{code}',
    name: 'test_page_test.http_response_exception',
    requirements: ['_access' => 'TRUE'],
    defaults: ['code' => 200],
  )]
  public function httpResponseException(int $code) {
    throw new HttpException($code);
  }

  #[Route(
    path: '/test-error',
    name: 'test_page_test.error',
    requirements: ['_access' => 'TRUE'],
    defaults: ['code' => 200],
  )]

  public function error() {
    trigger_error('foo', E_USER_NOTICE);
    return [
      '#markup' => 'Content',
    ];
  }

  #[Route(
    path: '/test-escaped-characters',
    name: 'test_page_test.escaped_characters',
    requirements: ['_access' => 'TRUE'],
    defaults: ['code' => 200],
  )]

  public function escapedCharacters() {
    return [
      '#prefix' => '<div class="escaped">',
      '#plain_text' => 'Escaped: <"\'&>',
      '#suffix' => '</div>',
    ];
  }

  #[Route(
    path: '/test-escaped-script',
    name: 'test_page_test.escaped_script',
    requirements: ['_access' => 'TRUE'],
    defaults: ['code' => 200],
  )]

  public function escapedScript() {
    return [
      '#prefix' => '<div class="escaped">',
      // We use #plain_text because #markup would be filtered and that is not
      // being tested here.
      '#plain_text' => "<script>alert('XSS');alert(\"XSS\");</script>",
      '#suffix' => '</div>',
    ];
  }

  public function unEscapedScript() {
    return [
      '#prefix' => '<div class="unescaped">',
      '#markup' => Markup::create("<script>alert('Marked safe');alert(\"Marked safe\");</script>"),
      '#suffix' => '</div>',
    ];
  }

  /**
   * Loads a page that does a redirect.
   *
   * Drupal uses Symfony's RedirectResponse for generating redirects. That class
   * uses a lower-case 'http-equiv="refresh"'.
   *
   * @see \Symfony\Component\HttpFoundation\RedirectResponse
   */
  #[Route(
    path: '/test-meta-refresh',
    name: 'test_page_test.meta_refresh',
    title: new TranslatableMarkup('Page with a redirect'),
    requirements: ['_access' => 'TRUE'],
  )]
  public function metaRefresh() {
    return new RedirectResponse(Url::fromRoute('test_page_test.test_page', [], ['absolute' => TRUE])->toString(), 302);
  }

  /**
   * Returns a page while triggering deprecation notices.
   */
  #[Route(
    path: '/test-deprecations',
    name: 'test_page_test.deprecations',
    title: new TranslatableMarkup('Page with deprecation notices'),
    requirements: ['_access' => 'TRUE'],
  )]
  public function deprecations() {
    // Create 2 identical deprecation messages. This should only trigger a
    // single response header.
    // phpcs:ignore Drupal.Semantics.FunctionTriggerError
    @trigger_error('Test deprecation message', E_USER_DEPRECATED);
    // phpcs:ignore Drupal.Semantics.FunctionTriggerError
    @trigger_error('Test deprecation message', E_USER_DEPRECATED);
    return [
      '#markup' => 'Content that triggers deprecation messages',
    ];
  }

}
