<?php

declare(strict_types=1);

namespace Drupal\test_htmx\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for HTMX Test Attachments routes.
 */
final class HtmxTestAttachmentsController extends ControllerBase {

  /**
   * Builds the response.
   *
   * @return mixed[]
   *   A render array.
   */
  public function page(): array {
    return self::generateHtmxButton();
  }

  /**
   * Builds a response with a `beforebegin` swap.
   *
   * @return mixed[]
   *   A render array.
   */
  public function before(): array {
    return self::generateHtmxButton('beforebegin');
  }

  /**
   * Builds a response with an `afterend` swap.
   *
   * @return mixed[]
   *   A render array.
   */
  public function after(): array {
    return self::generateHtmxButton('afterend');
  }

  /**
   * Builds a response with an the wrapper format parameter on the request.
   *
   * @return mixed[]
   *   A render array.
   */
  public function withWrapperFormat(): array {
    return self::generateHtmxButton('', TRUE);
  }

  /**
   * Builds the HTMX response.
   *
   * @return mixed[]
   *   A render array.
   */
  public function replace(): array {
    $build['content'] = [
      '#type' => 'container',
      '#attached' => [
        'library' => ['test_htmx/assets'],
      ],
      '#attributes' => [
        'class' => ['ajax-content'],
      ],
      'example' => ['#markup' => 'Initial Content'],
    ];

    return $build;
  }

  /**
   * We need a static callback that ignores callback parameters.
   *
   * @return array
   *   The render array.
   */
  public static function replaceWithAjax(): array {
    return static::generateHtmxButton();
  }

  /**
   * Static helper to for reusable render array.
   *
   * @return array
   *   The render array.
   */
  public static function generateHtmxButton(string $swap = '', bool $useWrapperFormat = FALSE): array {
    $options = [];
    if ($useWrapperFormat) {
      $options = [
        'query' => [
          MainContentViewSubscriber::WRAPPER_FORMAT => 'drupal_htmx',
        ],
      ];
    }
    $url = Url::fromRoute('test_htmx.attachments.replace', [], $options);
    $build['replace'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#attributes' => [
        'type' => 'button',
        'name' => 'replace',
      ],
      '#value' => 'Click this',
    ];
    $replace_htmx = (new Htmx())
      ->get($url, full_page: !$useWrapperFormat)
      ->select('div.ajax-content')
      ->target('[data-drupal-htmx-target]');
    if ($swap !== '') {
      $replace_htmx->swap($swap);
    }
    $replace_htmx->applyTo($build['replace']);

    $build['content'] = [
      '#type' => 'container',
      '#attributes' => [
        'data-drupal-htmx-target' => TRUE,
        'class' => ['htmx-test-container'],
      ],
    ];

    return $build;
  }

  /**
   * Responses for the requests made by in buildPageWithHtmxRequestsExamples.
   *
   * @return array
   *   The render array.
   */
  public function buildHtmxResponseByParams(Request $req): array {
    $query = $req->query->all();
    $method = $req->getMethod();

    $type = 'full-page';
    if (isset($query[MainContentViewSubscriber::WRAPPER_FORMAT]) && $query[MainContentViewSubscriber::WRAPPER_FORMAT] === 'drupal_htmx') {
      $type = 'drupal-htmx';
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => 'Hello, world Htmx ' . $type . ' ' . strtolower($method),
      '#attributes' => [
        'id' => 'wrapper-htmx-response-' . $type . '-' . strtolower($method),
        ...$query,
      ],
    ];
  }

  /**
   * Builds a page with HTMX request example for each http verb supported.
   *
   * @return array
   *   The render array.
   */
  public function buildPageWithHtmxRequestsExamples(): array {
    $base_button = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#attributes' => [
        'type' => 'button',
        'name' => 'replace',
      ],
    ];

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['htmx-test-container'],
      ],
    ];

    $url = Url::fromRoute('test_htmx.build_response_by_params');

    foreach (['get', 'post', 'delete', 'put', 'patch'] as $method) {
      $button = $base_button;
      $button['#value'] = 'Htmx Wrapper Format ' . $method;
      $button['#attributes']['id'] = 'button-drupal-htmx-' . $method;
      (new Htmx())
        ->{$method}($url)
        ->swap('outerHTML')
        ->trigger('click')
        ->applyTo($button);
      $build['button_' . $method] = $button;

      $button_full_page = $base_button;
      $button_full_page['#attributes']['id'] = 'button-full-page-' . $method;
      $button_full_page['#value'] = 'Htmx Full Page ' . $method;
      (new Htmx())
        ->{$method}($url, TRUE)
        ->swap('outerHTML')
        ->trigger('click')
        ->applyTo($button_full_page);
      $build['button_full_page_' . $method] = $button_full_page;
    }
    return $build;
  }

}
