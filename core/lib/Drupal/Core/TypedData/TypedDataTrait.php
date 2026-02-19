<?php

namespace Drupal\Core\TypedData;

/**
 * Wrapper methods for classes that needs typed data manager object.
 */
trait TypedDataTrait {

  /**
   * The typed data manager used for creating the data types.
   *
   * @var TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * Sets the typed data manager.
   *
   * @param TypedDataManagerInterface $typed_data_manager
   *   The typed data manager.
   *
   * @return $this
   */
  public function setTypedDataManager(TypedDataManagerInterface $typed_data_manager) {
    $this->typedDataManager = $typed_data_manager;
    return $this;
  }

  /**
   * Gets the typed data manager.
   *
   * @return TypedDataManagerInterface
   *   The typed data manager.
   */
  public function getTypedDataManager() {
    if (empty($this->typedDataManager)) {
      $this->typedDataManager = \Drupal::typedDataManager();
    }

    return $this->typedDataManager;
  }

}
