<?php

declare(strict_types=1);

namespace Drupal\user;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\user\Attribute\AccountCancelMethod;

/**
 * Manages user account cancellation method plugins.
 */
class AccountCancelMethodManager extends DefaultPluginManager {

  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cacheBackend,
    ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct('Plugin/user/CancelMethod', $namespaces, $moduleHandler, AccountCancelMethodInterface::class, AccountCancelMethod::class);
    $this->alterInfo('user_cancel_method_info');
    $this->setCacheBackend($cacheBackend, 'user_cancel_methods');
  }

}
