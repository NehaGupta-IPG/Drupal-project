<?php

namespace Drupal\Tests;

use Drupal\Component\FileSystem\FileSystem;

/**
 * Trait for creating a test ID from which derives the site path and DB prefix.
 */
trait SitePrefixTrait {

  /**
   * The test lock ID.
   *
   * A random number used to ensure that test fixtures are unique to each test
   * method.
   *
   * @var int
   */
  protected $lockId;

  /**
   * The test database prefix.
   *
   * @var string
   */
  protected $databasePrefix;

  /**
   * Generates a unique lock ID for the test method.
   *
   * @param boolean $create_lock
   *   (optional) Whether or not to create a lock file. Defaults to FALSE. If
   *   the environment variable RUN_TESTS_CONCURRENCY is greater than 1 it will
   *   be overridden to TRUE regardless of its initial value.
   *
   * @return int
   *   The unique lock ID for the test method.
   */
  protected function createTestLockId(bool $create_lock = FALSE): int {
    // There is a risk that the generated random number is a duplicate. This
    // would cause different tests to try to use the same database prefix.
    // Therefore, if running with a concurrency of greater than 1, we need to
    // create a lock.
    if (getenv('RUN_TESTS_CONCURRENCY') > 1) {
      $create_lock = TRUE;
    }

    do {
      $lock_id = mt_rand(10000000, 99999999);
      if ($create_lock && @symlink(__FILE__, $this->getLockFile($lock_id)) === FALSE) {
        // If we can't create a symlink, the lock ID is in use. Generate another
        // one. Symlinks are used because they are atomic and reliable.
        $lock_id = NULL;
      }
    } while ($lock_id === NULL);
    return $lock_id;
  }

  /**
   * Generates the database prefix for running tests and sets it as a property.
   *
   * The database prefix is used by prepareEnvironment() to setup a public files
   * directory for the test to be run, which also contains the PHP error log,
   * which is written to in case of a fatal error. Since that directory is based
   * on the database prefix, all tests (even unit tests) need to have one, in
   * order to access and read the error log.
   *
   * The generated database table prefix is used for the Drupal installation
   * being performed for the test. It is also used as user agent HTTP header it
   * is also used in the user agent HTTP header value by BrowserTestBase, which
   * is sent to the Drupal installation of the test. During early Drupal all
   * bootstrap, the user agent HTTP header is parsed, and if it matches,
   * database queries use the database table prefix that has been generated
   * here.
   *
   * @see \Drupal\Tests\BrowserTestBase::prepareEnvironment()
   * @see drupal_valid_test_ua()
   */
  protected function prepareDatabasePrefix() {
    if (!isset($this->databasePrefix)) {
      $this->databasePrefix = 'test' . $this->lockId;
    }
  }

  /**
   * Gets the relative path to the test site directory.
   *
   * @return string
   *   The relative path to the test site directory.
   */
  public function getTestSitePath(): string {
    return 'sites/simpletest/' . $this->lockId;
  }

  /**
   * Releases a lock.
   *
   * @return bool
   *   TRUE if successful, FALSE if not.
   */
  public function releaseLock(): bool {
    return unlink($this->getLockFile($this->lockId));
  }

  /**
   * Releases all test locks.
   *
   * This should only be called once all the test fixtures have been cleaned up.
   */
  public static function releaseAllTestLocks(): void {
    $tmp = FileSystem::getOsTemporaryDirectory();
    $dir = dir($tmp);
    while (($entry = $dir->read()) !== FALSE) {
      if ($entry === '.' || $entry === '..') {
        continue;
      }
      $entry_path = $tmp . '/' . $entry;
      if (preg_match('/^test_\d+/', $entry) && is_link($entry_path)) {
        unlink($entry_path);
      }
    }
  }

  /**
   * Gets the lock file path.
   *
   * @param int $lock_id
   *   The test method lock ID.
   *
   * @return string
   *   A file path to the symbolic link that prevents the lock ID being re-used.
   */
  protected function getLockFile(int $lock_id): string {
    return FileSystem::getOsTemporaryDirectory() . '/test_' . $lock_id;
  }

  /**
   * Gets the file path of the PHP error log of the test.
   *
   * @return string
   *   The relative path to the test site PHP error log file.
   */
  public function getPhpErrorLogPath(): string {
    return $this->getTestSitePath() . '/error.log';
  }

}
