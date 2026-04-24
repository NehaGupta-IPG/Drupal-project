<?php

namespace Drupal\Core\Config\Entity;

/**
 * Defines a trait for configuration entities that can be disabled.
 *
 * The protected property $status is defined on
 * \Drupal\Core\Config\Entity\ConfigEntityBase. In Drupal 13.0.0 it should be
 * removed and adding to this trait.
 */
trait StatusTrait {

  /**
   * Enables the configuration entity.
   *
   * @return $this
   *
   * @see \Drupal\Core\Config\Entity\ConfigEntityInterface::enable()
   */
  public function enable() {
    return $this->setStatus(TRUE);
  }

  /**
   * Disables the configuration entity.
   *
   * @return $this
   *
   * @see \Drupal\Core\Config\Entity\ConfigEntityInterface::disable()
   */
  public function disable() {
    return $this->setStatus(FALSE);
  }

  /**
   * Returns whether the configuration entity is enabled.
   *
   * Status implementations for configuration entities should follow these
   * general rules:
   *   - Status does not affect the loading of entities. I.e. Disabling
   *     configuration entities should only have UI/access implications.
   *   - It should only take effect when a 'status' key is explicitly declared
   *     in the entity_keys info of a configuration entity's annotation data.
   *   - Each entity implementation (entity/controller) is responsible for
   *     checking and managing the status.
   *
   * @return bool
   *   Whether the entity is enabled or not.
   */
  public function status() {
    return !empty($this->status);
  }

  /**
   * Sets the status of the configuration entity.
   *
   * @param bool $status
   *   The status of the configuration entity.
   *
   * @return $this
   */
  public function setStatus($status) {
    $this->status = (bool) $status;
    return $this;
  }

}
