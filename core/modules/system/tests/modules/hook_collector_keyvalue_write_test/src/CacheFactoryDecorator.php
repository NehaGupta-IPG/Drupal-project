<?php

declare(strict_types=1);

namespace Drupal\hook_collector_keyvalue_write_test;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Decorates the cache factory with a dependency on the current user.
 *
 * The current user service needs the event dispatcher. Thus, when
 * HookCollectorKeyValueWritePass gets the cache.bootstrap service, it also
 * creates the event dispatcher.
 */
class CacheFactoryDecorator implements CacheFactoryInterface {

  public function __construct(
    protected CacheFactoryInterface $inner,
    protected AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    return $this->inner->get($bin);
  }

}
