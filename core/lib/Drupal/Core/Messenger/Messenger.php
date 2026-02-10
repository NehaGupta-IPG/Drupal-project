<?php

namespace Drupal\Core\Messenger;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Render\Markup;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * The messenger service.
 */
class Messenger implements MessengerInterface {

  /**
   * The kill switch.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killSwitch;

  /**
   * Messenger constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The flash bag.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $killSwitch
   *   The kill switch.
   */
  public function __construct(protected readonly RequestStack $requestStack, KillSwitch $killSwitch) {
    $this->killSwitch = $killSwitch;
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

    $session = $this->requestStack->getSession();
    assert($session instanceof FlashBagAwareSessionInterface);
    $flashBag = $session->getFlashBag();

    // Do not use strict type checking so that equivalent string and
    // MarkupInterface objects are detected.
    if ($repeat || !in_array($message, $flashBag->peek($type))) {
      $flashBag->add($type, $message);
    }

    // Mark this page as being uncacheable.
    $this->killSwitch->trigger();

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
    $session = $this->requestStack->getSession();
    assert($session instanceof FlashBagAwareSessionInterface);
    return $session->getFlashBag()->peekAll();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $session = $this->requestStack->getSession();
    assert($session instanceof FlashBagAwareSessionInterface);
    return $session->getFlashBag()->clear();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteByType($type) {
    // Flash bag gets and clears flash messages from the stack.
    $session = $this->requestStack->getSession();
    assert($session instanceof FlashBagAwareSessionInterface);
    return $session->getFlashBag()->get($type);
  }

  /**
   * {@inheritdoc}
   */
  public function messagesByType($type) {
    $session = $this->requestStack->getSession();
    assert($session instanceof FlashBagAwareSessionInterface);
    return $session->getFlashBag()->peek($type);
  }

}
