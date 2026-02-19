<?php

namespace Drupal\Core\Messenger;

/**
 * Provides a trait for the messenger service.
 */
trait MessengerTrait {

  /**
   * The messenger.
   *
   * @var MessengerInterface
   */
  protected $messenger;

  /**
   * Sets the messenger.
   *
   * @param MessengerInterface $messenger
   *   The messenger.
   */
  public function setMessenger(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * Gets the messenger.
   *
   * @return MessengerInterface
   *   The messenger.
   */
  public function messenger() {
    if (!isset($this->messenger)) {
      $this->messenger = \Drupal::messenger();
    }
    return $this->messenger;
  }

}
