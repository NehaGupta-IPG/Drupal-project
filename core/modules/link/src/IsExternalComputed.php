<?php

namespace Drupal\link;

use Drupal\Core\TypedData\TypedData;

/**
 * A computed property for processing if a link field's URL is external.
 */
class IsExternalComputed extends TypedData {

  /**
   * Cached is_external value.
   *
   * @var bool|null
   */
  protected $isExternal = NULL;

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->isExternal;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE): void {
    $item = $this->getParent();
    $this->isExternal = FALSE;
    if (!$item->isEmpty()) {
      $this->isExternal = $item->isExternal();
    }
  }

}
