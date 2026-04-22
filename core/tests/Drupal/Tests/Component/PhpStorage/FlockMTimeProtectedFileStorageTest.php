<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\PhpStorage;

use Drupal\Component\PhpStorage\FlockMTimeProtectedFileStorage;
use Drupal\Component\PhpStorage\LockingPhpStorageInterface;
use Drupal\Component\Utility\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\Component\Finder\Finder;

/**
 * Tests FlockMTimeProtectedFileStorage.
 *
 * Tests run against a real filesystem directory because PHP's flock() is not
 * supported by vfsStream.
 */
#[CoversClass(FlockMTimeProtectedFileStorage::class)]
#[Group('Drupal')]
#[Group('PhpStorage')]
#[Medium]
class FlockMTimeProtectedFileStorageTest extends MTimeProtectedFileStorageBase {

  /**
   * The expected test results for the security test.
   *
   * FlockMTimeProtectedFileStorage extends MTimeProtectedFileStorage, which
   * checks the file mtime against the directory mtime on every load(). Both
   * attack iterations therefore return FALSE.
   *
   * @var bool[]
   */
  protected array $expected = [FALSE, FALSE];

  /**
   * The PHP storage class to test.
   *
   * @var class-string
   */
  protected $storageClass = FlockMTimeProtectedFileStorage::class;

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line phpunit.callParent
   */
  protected function setUp(): void {
    // Do not call parent::setUp(): it initialises vfsStream, which does not
    // support flock(). Use a real temporary directory instead.
    $this->directory = sys_get_temp_dir() . '/drupal_flock_test_' . uniqid('', TRUE);
    mkdir($this->directory, 0777, TRUE);

    $random = new Random();
    $this->secret = $random->name(8, TRUE);
    $this->settings = [
      'directory' => $this->directory,
      'bin' => 'test',
      'secret' => $this->secret,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Recursively removes the directory, restoring permissions as needed.
    $fs = new SymfonyFilesystem();
    $finder = new Finder();
    $finder->in($this->directory)
      ->directories()
      ->ignoreVCS(FALSE)
      ->ignoreDotFiles(FALSE);
    $fs->chmod($finder->getIterator(), 0775, 0000);
    $fs->remove($this->directory);

    parent::tearDown();
  }

  /**
   * Tests that the class implements LockingPhpStorageInterface.
   */
  public function testImplementsLockingInterface(): void {
    $storage = new FlockMTimeProtectedFileStorage($this->settings);
    $this->assertInstanceOf(LockingPhpStorageInterface::class, $storage);
  }

  /**
   * Tests that save() returns FALSE when another caller holds the lock.
   *
   * Manually acquiring the lock file with flock() simulates a concurrent PHP
   * worker in the same process space.
   */
  public function testSaveReturnsFalseWhenLockAlreadyHeld(): void {
    $storage = new FlockMTimeProtectedFileStorage($this->settings);
    $name = 'test_concurrent.php';

    // Perform an initial save to ensure the storage directory and lock file
    // are created before the test acquires the lock manually.
    $this->assertTrue($storage->save($name, '<?php'));

    // Simulate a competing worker by holding an exclusive non-blocking lock
    // on the same lock file the storage uses.
    $lockFile = $this->directory . '/test/' . $name . '.lock';
    $fd = fopen($lockFile, 'c');
    $this->assertTrue(flock($fd, LOCK_EX | LOCK_NB), 'Test could not acquire manual lock to set up the scenario');

    // save() must return FALSE immediately without blocking.
    $this->assertFalse($storage->save($name, '<?php // blocked write'));

    flock($fd, LOCK_UN);
    fclose($fd);
  }

  /**
   * Tests that save() succeeds once a competing lock is released.
   */
  public function testSaveSucceedsAfterLockReleased(): void {
    $storage = new FlockMTimeProtectedFileStorage($this->settings);
    $name = 'test_retry.php';

    $this->assertTrue($storage->save($name, '<?php'));

    $lockFile = $this->directory . '/test/' . $name . '.lock';
    $fd = fopen($lockFile, 'c');
    flock($fd, LOCK_EX | LOCK_NB);

    $this->assertFalse($storage->save($name, '<?php // blocked'));

    // Releasing the lock must allow the next save() to proceed.
    flock($fd, LOCK_UN);
    fclose($fd);

    $this->assertTrue($storage->save($name, '<?php // unblocked'));
  }

  /**
   * Tests that the lock is released after a successful save().
   *
   * Verifies the finally block runs correctly: another caller can acquire the
   * same lock immediately after save() returns.
   */
  public function testLockIsReleasedAfterSuccessfulSave(): void {
    $storage = new FlockMTimeProtectedFileStorage($this->settings);
    $name = 'test_release.php';

    $this->assertTrue($storage->save($name, '<?php'));

    // After save() returns, the lock must be free. A non-blocking attempt to
    // acquire it must succeed.
    $lockFile = $this->directory . '/test/' . $name . '.lock';
    $fd = fopen($lockFile, 'c');
    $acquired = flock($fd, LOCK_EX | LOCK_NB);
    flock($fd, LOCK_UN);
    fclose($fd);

    $this->assertTrue($acquired, 'save() did not release the lock');
  }

  /**
   * Tests that names containing path separators are hashed for the lock file.
   *
   * Without hashing, a name like 'subdir/file.php' would cause fopen() to
   * attempt creating 'subdir/file.php.lock' inside a non-existent subdirectory,
   * making createLock() fail and save() return FALSE. A successful save()
   * proves getLockName() correctly flattened the name to a hash.
   */
  public function testSaveWithSlashedNameHashesLockFile(): void {
    $storage = new FlockMTimeProtectedFileStorage($this->settings);
    $name = 'subdir/test_hashed.php';

    $this->assertTrue($storage->save($name, '<?php'), 'save() must succeed for names containing path separators');

    // The lock file must use the hashed name, not the raw name with a slash.
    $storageDir = $this->directory . '/test';
    $hashedLock = $storageDir . '/' . hash('xxh128', $name) . '.lock';
    $this->assertFileExists($hashedLock, 'Lock file must exist at the hashed path');
  }

}
