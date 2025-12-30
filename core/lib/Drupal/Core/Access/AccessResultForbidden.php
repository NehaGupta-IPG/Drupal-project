<?php

namespace Drupal\Core\Access;

/**
 * Value object for a forbidden access result, with cacheability metadata.
 */
class AccessResultForbidden extends AccessResult implements AccessResultReasonInterface {

  /**
   * The reason why access is forbidden. For use in error messages.
   *
   * @var string
   */
  protected $reason;

  public function __construct($reason = NULL) {
    $this->reason = $reason;
  }

  /**
   * {@inheritdoc}
   */
  public function isForbidden() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getReason() {
    return (string) $this->reason;
  }

  /**
   * {@inheritdoc}
   */
  public function setReason($reason) {
    $this->reason = $reason;
    return $this;
  }

}
