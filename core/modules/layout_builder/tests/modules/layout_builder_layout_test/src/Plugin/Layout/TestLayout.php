<?php

declare(strict_types=1);

namespace Drupal\layout_builder_layout_test\Plugin\Layout;

use Drupal\Core\Layout\Attribute\Layout;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The Layout Builder Test Plugin.
 */
#[Layout(
  id: 'layout_builder_test_layout',
  label: new TranslatableMarkup('Layout Builder Test Layout'),
  regions: [
    "main" => [
      "label" => new TranslatableMarkup("Main Region"),
    ],
  ],
)]
class TestLayout extends LayoutDefault {

  /**
   * {@inheritdoc}
   */
  public function build(array $regions) {
    $build = parent::build($regions);
    $build['main']['#attributes']['class'][] = 'testing-layout';
    if ($this->inPreview) {
      $build['main']['#attributes']['class'][] = 'testing-layout-preview';
    }
    return $build;
  }

}
