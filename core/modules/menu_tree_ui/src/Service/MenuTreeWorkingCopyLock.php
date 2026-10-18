<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Service;

use Drupal\Core\Lock\LockBackendInterface;

/**
 * Serializes the requests that read or write a menu's working copy.
 *
 * Recording a move, Save, Cancel and breaking the lock each hold this for the
 * menu while they run, so one cannot change the working copy under another.
 *
 * @internal
 */
final class MenuTreeWorkingCopyLock {

  public function __construct(
    private readonly LockBackendInterface $lock,
  ) {}

  /**
   * Acquires the menu's lock, waiting up to 5 seconds for a current holder.
   *
   * The lock expires after the PHP time limit (at least 30 seconds, or 120
   * when there is none), so a request killed before it can release it does not
   * hold the menu indefinitely.
   */
  public function acquire(string $menu_name): bool {
    $name = $this->name($menu_name);
    $max_execution_time = (int) ini_get('max_execution_time');
    $expiry = $max_execution_time > 0 ? max((float) $max_execution_time, 30.0) : 120.0;
    if ($this->lock->acquire($name, $expiry)) {
      return TRUE;
    }
    $this->lock->wait($name, 5);
    return $this->lock->acquire($name, $expiry);
  }

  public function release(string $menu_name): void {
    $this->lock->release($this->name($menu_name));
  }

  private function name(string $menu_name): string {
    return 'menu_tree_ui.working_copy:' . $menu_name;
  }

}
