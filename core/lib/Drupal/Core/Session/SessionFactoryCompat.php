<?php

declare(strict_types=1);

namespace Drupal\Core\Session;

use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Compatibility for custom session bags registered via service tag.
 */
class SessionFactoryCompat implements SessionFactoryInterface {

  /**
   * @var \Symfony\Component\HttpFoundation\Session\SessionBagInterface[]
   */
  protected array $bags = [];

  public function __construct(protected readonly SessionFactoryInterface $inner) {
  }

  /**
   * {@inheritdoc}
   */
  public function createSession(): SessionInterface {
    $session = $this->inner->createSession();
    foreach ($this->bags as $bag) {
      $session->registerBag($bag);
    }
    return $session;
  }

  /**
   * Registers a session bag.
   *
   * @param Symfony\Component\HttpFoundation\Session\SessionBagInterface $bag
   *   Session bag to register.
   *
   * @todo DEPRECATE
   */
  public function registerBag(SessionBagInterface $bag): void {
    $this->bags[$bag->getName()] = $bag;
  }

}
