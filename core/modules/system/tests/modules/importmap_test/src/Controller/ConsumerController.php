<?php

declare(strict_types=1);

namespace Drupal\importmap_test\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns pages that attach import map consumer libraries.
 */
class ConsumerController extends ControllerBase {

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
