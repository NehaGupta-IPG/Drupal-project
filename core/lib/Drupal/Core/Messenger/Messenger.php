<?php

namespace Drupal\Core\Messenger;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Render\Markup;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The messenger service.
 */
class Messenger implements MessengerInterface {

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * The kill switch.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killSwitch;

  /**
   * A boolean to indicate if a message has been set.
   *
   * @var bool
   */
  protected $messageSet = FALSE;

  /**
   * Messenger constructor.
   *
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $killSwitch
   *   The kill switch.
   */
  public function __construct(SessionInterface $session, KillSwitch $killSwitch) {
    $this->session = $session;
    $this->killSwitch = $killSwitch;
  }

  /**
   * Gets the flash bag.
   *
   * @return \Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface
   *   The flash bag.
   */
  protected function getFlashBag() {
    return $this->session->getFlashBag();
  }

  /**
   * {@inheritdoc}
   */
  public function addError($message, $repeat = FALSE) {
    return $this->addMessage($message, static::TYPE_ERROR, $repeat);
  }

  /**
   * {@inheritdoc}
   */
  public function addMessage($message, $type = self::TYPE_STATUS, $repeat = FALSE) {
    if (!($message instanceof Markup) && $message instanceof MarkupInterface) {
      $message = Markup::create((string) $message);
    }

    // Do not use strict type checking so that equivalent string and
    // MarkupInterface objects are detected.
    if ($repeat || !in_array($message, $this->getFlashBag()->peek($type))) {
      $this->getFlashBag()->add($type, $message);
    }

    // Mark this page as being uncacheable.
    $this->killSwitch->trigger();
    $this->messageSet = TRUE;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addStatus($message, $repeat = FALSE) {
    return $this->addMessage($message, static::TYPE_STATUS, $repeat);
  }

  /**
   * {@inheritdoc}
   */
  public function addWarning($message, $repeat = FALSE) {
    return $this->addMessage($message, static::TYPE_WARNING, $repeat);
  }

  /**
   * {@inheritdoc}
   */
  public function all() {
    return $this->flashBag->peekAll();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    if ($this->noMessages()) {
      return [];
    }
    return $this->getFlashBag()->clear();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteByType($type) {
    if ($this->noMessages()) {
      return [];
    }
    // Flash bag gets and clears flash messages from the stack.
    return $this->getFlashBag()->get($type);
  }

  /**
   * {@inheritdoc}
   */
  public function messagesByType($type) {
    if ($this->noMessages()) {
      return [];
    }
    // Flash bag gets and clears flash messages from the stack.
    return $this->getFlashBag()->peek($type);
  }

  /**
   * Determines if there are no messages.
   *
   * If the session has not started and $this->messageSet there cannot be any
   * messages.
   *
   * @return bool
   *   TRUE if there are no messages, FALSE if not.
   */
  protected function noMessages() {
    return !$this->session->isStarted() && !$this->messageSet;
  }

}
