<?php

namespace Drupal\link;

use Drupal\Core\TypedData\TypedData;

/**
 * A computed property for processing if link is external.
 */
class IsExternalProcessed extends TypedData {

  /**
   * Cached is external value.
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
  public function setValue($value, $notify = TRUE) {
    $item = $this->getParent();
    $this->isExternal = FALSE;
    if (!$item->isEmpty()) {
      $this->isExternal = $item->isExternal();
    }
  }

}
