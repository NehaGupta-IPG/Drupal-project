<?php

namespace Drupal\Core\Extension\Install;

/**
 * Methods for setting last removed update hooks.
 *
 * Classes implementing this must be in the Install\LastRemoved namespace.
 * For example src/Install/LastRemoved/ModuleNameLastRemoved.php.
 */
interface LastRemovedInterface {

  /**
   * Return a number which is no longer available as hook_update_N().
   *
   * If you remove some update functions from your my_module.install file, you
   * should notify Drupal of those missing functions. This way, Drupal can
   * ensure that no update is accidentally skipped.
   *
   * Implementations of this hook should be placed in a my_module.install file in
   * the same directory as my_module.module.
   *
   * @return int
   *   An integer, corresponding to hook_update_N() which has been removed from
   *   my_module.install.
   *
   * @ingroup update_api
   *
   * @see hook_update_N()
   */
  public function getLastRemoved(): int;

}
