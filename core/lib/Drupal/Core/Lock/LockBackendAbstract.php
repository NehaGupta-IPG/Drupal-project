<?php

namespace Drupal\Core\Lock;

/**
 * Non backend related common methods implementation for lock backends.
 *
 * @ingroup lock
 */
abstract class LockBackendAbstract implements LockBackendInterface {

  /**
   * Current page lock token identifier.
   *
   * @var string
   */
  protected $lockId;

  /**
   * Existing locks for this page.
   *
   * @var array
   */
  protected $locks = [];

  /**
   * {@inheritdoc}
   */
  public function wait($name, $delay = 30) {
    // Pause the process for short periods between calling
    // lock_may_be_available(). This prevents hitting the database with constant
    // database queries while waiting, which could lead to performance issues.
    // However, if the wait period is too long, there is the potential for a
    // large number of processes to be blocked waiting for a lock, especially
    // if the item being rebuilt is commonly requested. To address both of these
    // concerns, begin waiting for 25ms, then add 25ms to the wait period each
    // time until it reaches 500ms. After this point polling will continue every
    // 500ms until $delay is reached.

    // $delay is passed in seconds, but we will be using usleep(), which takes
    // microseconds as a parameter. Multiply it by 1 million so that all
    // further numbers are equivalent.
    $delay = (int) $delay * 1000000;

    // Begin sleeping at 25ms.
    $sleep = 25000;
    while ($delay > 0) {
      // Check if we're executing inside a Fiber. If so, before sleeping,
      // suspend the fiber in case some other code can run in the meantime. By
      // the time that code has finished running, the lock may already be
      // available.
      // @see Drupal\Core\Prewarm\CachePrewarmer
      if (\Fiber::getCurrent() !== NULL) {
        \Fiber::suspend();
      }
      if ($this->lockMayBeAvailable($name)) {
        // No longer need to wait.
        return FALSE;
      }
      // If the lock is still not available, it's possible that the parent
      // process immediately resumed the Fiber we're running in, so sleep
      // to avoid a lock stampede.
      usleep($sleep);
      // Also to avoid a lock stampede, slowly increase the value of $sleep
      // the longer we wait, until it reaches 500ms.
      $delay = $delay - $sleep;
      $sleep = min(500000, $sleep + 25000, $delay);
      if ($this->lockMayBeAvailable($name)) {
        return FALSE;
      }
    }
    // The caller must still wait longer to get the lock.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getLockId() {
    if (!isset($this->lockId)) {
      $this->lockId = uniqid((string) mt_rand(), TRUE);
    }
    return $this->lockId;
  }

}
