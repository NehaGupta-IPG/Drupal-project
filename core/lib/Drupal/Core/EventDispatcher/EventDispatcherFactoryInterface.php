<?php

declare(strict_types=1);

namespace Drupal\Core\EventDispatcher;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides an interface for the event dispatcher factory.
 */
interface EventDispatcherFactoryInterface extends EventDispatcherInterface {

  /**
   * Creates the event dispatcher singleton.
   *
   * This method can be called multiple times during a request; each time a new
   * instance is created, replacing the previous one with all its listeners.
   * The stage of the request when the singleton is created is also stored.
   *
   * @param \Drupal\Core\EventDispatcher\EventDispatcherFactoryStage|string $stage
   *   (optional) The stage of the request when the singleton was created.
   *   Defaults to EventDispatcherFactoryStage::PreBootstrap.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher.
   *
   * @see \Drupal\Core\EventDispatcher\EventDispatcherFactoryStage
   */
  public static function createInstance(EventDispatcherFactoryStage|string $stage = EventDispatcherFactoryStage::PreBootstrap): EventDispatcherInterface;

  /**
   * Registers early subscribers with the pre-bootstrap dispatcher.
   *
   * This method can be called multiple times prior to full container
   * availability to ensure event subscribers are ready. Event subscribers in
   * any case need to be repeated in the container definition file; those will
   * replace the early ones once the container is fully built.
   * Calling this method on the event dispatcher available from the service
   * container has no effects.
   *
   * @param string $group
   *   An identifier of the group of subscribers registered by the callback. It
   *   is used to prevent subscribers to be duplicated.
   * @param callable $callback
   *   A callable that instantiates the subscribers and registers them with the
   *   dispatcher.
   *
   * @return \Drupal\Core\EventDispatcher\EventDispatcherFactoryInterface
   *   The event dispatcher factory.
   */
  public function registerEarlySubscribers(string $group, callable $callback): static;

  /**
   * Returns the stage of the event dispatcher singleton.
   *
   * @return \Drupal\Core\EventDispatcher\EventDispatcherFactoryStage
   *   The stage of the request when the singleton was created.
   *
   * @throws \LogicException
   *   If the singleton is not initialized yet.
   */
  public function getInstanceStage(): EventDispatcherFactoryStage;

}
