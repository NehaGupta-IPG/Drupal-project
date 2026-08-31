<?php

declare(strict_types=1);

namespace Drupal\importmap_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Returns pages that attach import map consumer libraries.
 */
class ConsumerController extends ControllerBase implements TrustedCallbackInterface {

  /**
   * Returns a page that attaches the consumer library.
   *
   * The consumer library loads an ES module that imports the bare module
   * specifier 'importmap-test-foo', which is resolved by the import map.
   */
  public function consumerPage(): array {
    return $this->page('importmap_test/consumer');
  }

  /**
   * Returns a page that attaches the scoped consumer library.
   *
   * The consumer is loaded from the 'js/scope' path, so the
   * 'importmap-test-foo' import resolves to the scoped mapping.
   */
  public function scopedConsumerPage(): array {
    return $this->page('importmap_test/scoped-consumer');
  }

  /**
   * Returns a page with a BigPipe placeholder that attaches the consumer.
   *
   * The lazy builder becomes a BigPipe placeholder when the big_pipe module
   * is enabled. Its replacement attaches a library whose module script
   * imports a bare module specifier, resolved by the import map sent with
   * the main HTML document.
   */
  public function bigPipeConsumerPage(): array {
    return [
      '#type' => 'markup',
      '#markup' => 'BigPipe import map test page.',
      'consumer' => [
        '#lazy_builder' => [
          '\Drupal\importmap_test\Controller\ConsumerController::lazyConsumerPage',
          ['importmap_test/consumer'],
        ],
        // The max-age of 0 ensures that the element is auto-placeholdered,
        // so that it becomes a BigPipe placeholder when the big_pipe module
        // is enabled.
        '#cache' => ['max-age' => 0],
      ],
    ];
  }

  /**
   * Lazy builder callback that returns a page that attaches the given library.
   *
   * @param string $library
   *   The library to attach.
   *
   * @return array
   *   A render array.
   */
  public static function lazyConsumerPage(string $library): array {
    return [
      '#type' => 'markup',
      '#markup' => 'Import map consumer content.',
      '#attached' => [
        'library' => [$library],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['lazyConsumerPage'];
  }

  /**
   * Returns a page that attaches the given library.
   *
   * @param string $library
   *   The library to attach.
   *
   * @return array
   *   A render array.
   */
  protected function page(string $library): array {
    return [
      '#type' => 'markup',
      '#markup' => 'Import map test page.',
      '#attached' => [
        'library' => [$library],
      ],
    ];
  }

}
