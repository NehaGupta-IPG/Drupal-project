<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Defines a controller to provide the Layout Builder admin UI.
 *
 * @internal
 *   Controller classes are internal.
 */
class LayoutBuilderController {

  use StringTranslationTrait;

  /**
   * Provides a title callback.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title for the layout page.
   */
  public function title(SectionStorageInterface $section_storage): TranslatableMarkup {
    return $section_storage->getEditLabel();
  }

  /**
   * Renders the Layout UI.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return array
   *   A render array.
   */
  public function layout(SectionStorageInterface $section_storage) {
    return [
      '#type' => 'layout_builder',
      '#section_storage' => $section_storage,
    ];
  }

}
