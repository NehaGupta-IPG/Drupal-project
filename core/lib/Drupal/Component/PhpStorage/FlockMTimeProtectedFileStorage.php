<?php

declare(strict_types=1);

namespace Drupal\Component\PhpStorage;

/**
 * MTimeProtectedFileStorage with non-blocking flock()-based locking.
 *
 * Locking uses flock() with LOCK_EX | LOCK_NB, which the OS releases
 * automatically if the holding process terminates for any reason.
 *
 * flock() is reliable only on local filesystems. This class must therefore be
 * configured to store files in a local directory such as sys_get_temp_dir(),
 * never on a shared NFS mount. Each web head maintains its own compiled copy.
 *
 * @see \Drupal\Core\PhpStorage\PhpStorageFactory
 */
class FlockMTimeProtectedFileStorage extends MTimeProtectedFileStorage implements LockingPhpStorageInterface {

  /**
   * Open file descriptors for currently held locks, keyed by lock name.
   *
   * The descriptor must remain open for the duration of the lock; closing it
   * (or process exit) releases the flock() lock automatically.
   *
   * @var resource[]
   */
  protected array $lockHandles = [];

  /**
   * {@inheritdoc}
   */
  public function save($name, $data) {
    $lock = $this->createLock($name);
    if (!$lock) {
      return FALSE;
    }
    try {
      return parent::save($name, $data);
    }
    finally {
      $this->releaseLock($name);
    }
  }

  /**
   * Attempts to acquire an advisory lock.
   *
   * The operation must be atomic: of any number of concurrent callers passing
   * the same $name, exactly one must receive TRUE.
   *
   * @param string $name
   *   The virtual file name. Can be a relative path.
   *
   * @return bool
   *   TRUE if the lock was acquired, FALSE if another caller holds it.
   *
   * @see \Symfony\Component\HttpKernel\Kernel::initializeContainer()
   */
  protected function createLock(string $name): bool {
    $name = $this->getLockName($name);
    $this->ensureDirectory($this->directory);
    $fd = fopen($this->directory . '/' . $name . '.lock', 'c');
    if ($fd === FALSE) {
      return FALSE;
    }

    // Try a non-blocking exclusive lock first. If it fails because another
    // process holds the lock ($wouldBlock is true), bail out immediately —
    // another writer is active and we must not queue behind it. If it fails
    // for any other reason ($wouldBlock is false), retry with a blocking
    // exclusive lock. This handles NFS and other filesystems where LOCK_NB
    // can fail.
    if (!flock($fd, \LOCK_EX | \LOCK_NB, $wouldBlock)) {
      if ($wouldBlock || !flock($fd, \LOCK_EX)) {
        fclose($fd);
        return FALSE;
      }
    }

    $this->lockHandles[$name] = $fd;
    return TRUE;
  }

  /**
   * Releases a previously acquired lock.
   *
   * @param string $name
   *   The same identifier passed to createLock().
   */
  protected function releaseLock(string $name): void {
    $name = $this->getLockName($name);
    if (isset($this->lockHandles[$name])) {
      flock($this->lockHandles[$name], LOCK_UN);
      fclose($this->lockHandles[$name]);
      unset($this->lockHandles[$name]);
    }
  }

  /**
   * Returns the lock filename for a given virtual file name.
   *
   * If $name contains a path separator it is hashed to produce a flat,
   * filesystem-safe identifier.
   *
   * @param string $name
   *   The virtual file name passed to createLock() or releaseLock().
   *
   * @return string
   *   A filesystem-safe lock identifier.
   */
  protected function getLockName(string $name): string {
    if (str_contains($name, '/')) {
      $name = hash('xxh128', $name);
    }
    return $name;
  }

}
