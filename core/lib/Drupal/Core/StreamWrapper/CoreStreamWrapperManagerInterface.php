<?php

namespace Drupal\Core\StreamWrapper;

/**
 * Provides StreamWrapper manager methods utilized by Core.
 *
 * @see \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
 *
 * @internal
 *   These methods are necessary for a StreamWrapper manager to implement
 *   however unlike methods on StreamWrapperManagerInterface these methods
 *   generally should only be invoked by Drupal Core and not third parties.
 */
interface CoreStreamWrapperManagerInterface {

  /**
   * Adds a stream wrapper.
   *
   * @param string $service_id
   *   The service id.
   * @param string $class
   *   The stream wrapper class.
   * @param string $scheme
   *   The scheme for which the wrapper should be registered.
   */
  public function addStreamWrapper(string $service_id, string $class, string $scheme): void;

  /**
   * Registers the tagged stream wrappers.
   */
  public function register(): void;

  /**
   * Un-registers the tagged stream wrappers.
   */
  public function unregister(): void;

}
