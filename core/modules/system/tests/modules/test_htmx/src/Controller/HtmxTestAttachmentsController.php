<?php

declare(strict_types=1);

namespace Drupal\test_htmx\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Routing\Attribute\Route;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

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
  #[Route(
    path: '/htmx-test-attachments/page',
    name: 'test_htmx.attachments.page',
    title: new TranslatableMarkup('Page'),
    requirements: ['_permission' => 'access content'],
  )]
  public function page(): array {
    return self::generateHtmxButton();
  }

  /**
   * Builds a response with a `beforebegin` swap.
   *
   * @return mixed[]
   *   A render array.
   */
  #[Route(
    path: '/htmx-test-attachments/before',
    name: 'test_htmx.attachments.before',
    title: new TranslatableMarkup('Page'),
    requirements: ['_permission' => 'access content'],
  )]
  public function before(): array {
    return self::generateHtmxButton(swap: 'beforebegin');
  }

  /**
   * Builds a response with an `afterend` swap.
   *
   * @return mixed[]
   *   A render array.
   */
  #[Route(
    path: '/htmx-test-attachments/after',
    name: 'test_htmx.attachments.after',
    title: new TranslatableMarkup('Page'),
    requirements: ['_permission' => 'access content'],
  )]
  public function after(): array {
    return self::generateHtmxButton(swap: 'afterend');
  }

  /**
   * Builds a response with an the wrapper format parameter on the request.
   *
   * @return mixed[]
   *   A render array.
   */
  #[Route(
    path: '/htmx-test-attachments/wrapper',
    name: 'test_htmx.attachments.wrapper',
    title: new TranslatableMarkup('Minimal Response'),
    requirements: ['_permission' => 'access content'],
  )]
  public function withWrapperFormat(): array {
    return self::generateHtmxButton(swap: '', useWrapperFormat: TRUE);
  }

  /**
   * Tests body targeting and swapping.
   *
   * @return mixed[]
   *   A render array.
   */
  #[Route(
    path: '/htmx-test-attachments/body',
    name: 'test_htmx.attachments.body',
    title: new TranslatableMarkup('Body testing'),
    requirements: ['_permission' => 'access content'],
  )]
  public function selectBody(): array {
    return [
      '#title' => $this->t('Boosted body'),
      '#type' => 'link',
      '#url' => Url::fromRoute('test_htmx.attachments.replace'),
      '#attributes' => [
        'class' => ['htmx-test-link'],
      ],
    ];
  }

  /**
   * Builds the HTMX response.
   *
   * @return mixed[]
   *   A render array.
   */
  #[Route(
    path: '/htmx-test-attachments/replace',
    name: 'test_htmx.attachments.replace',
    title: new TranslatableMarkup('Ajax Content'),
    requirements: ['_permission' => 'access content'],
  )]
  #[Route(
    path: '/htmx-test-attachments/route-option',
    name: 'test_htmx.attachments.route_option',
    title: new TranslatableMarkup('Using _htmx_route option'),
    requirements: ['_permission' => 'access content'],
    options: ['_htmx_route' => TRUE],
  )]
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
    $url = Url::fromRoute('test_htmx.attachments.replace');
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
      ->get($url)
      ->onlyMainContent($useWrapperFormat)
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

}
