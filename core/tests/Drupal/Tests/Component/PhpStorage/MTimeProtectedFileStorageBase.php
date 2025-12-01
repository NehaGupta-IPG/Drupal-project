<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\PhpStorage;

use Drupal\Component\FileSecurity\FileSecurity;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Random;

/**
 * Base test class for MTime protected storage.
 */
abstract class MTimeProtectedFileStorageBase extends PhpStorageTestBase {

  /**
   * Whether to simulate filemtime() failure by deleting file before call.
   *
   * @var bool
   */
  public static bool $simulateFilemtimeFailure = FALSE;

  /**
   * The PHP storage class to test.
   *
   * This should be overridden by extending classes.
   *
   * @var string
   */
  protected $storageClass;

  /**
   * The secret string to use for file creation.
   *
   * @var string
   */
  protected $secret;

  /**
   * Test settings to pass to storage instances.
   *
   * @var array
   */
  protected $settings;

  /**
   * The expected test results for the security test.
   */
  protected array $expected;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Random generator.
    $random = new Random();

    $this->secret = $random->name(8, TRUE);

    $this->settings = [
      'directory' => $this->directory,
      'bin' => 'test',
      'secret' => $this->secret,
    ];
  }

  /**
   * Tests basic load/save/delete operations.
   */
  public function testCRUD(): void {
    $php = new $this->storageClass($this->settings);
    $this->assertCRUD($php);
  }

  /**
   * Tests the security of the MTimeProtectedFileStorage implementation.
   *
   * We test two attacks: first changes the file mtime, then the directory
   * mtime too.
   *
   * We need to delay over 1 second for mtime test.
   */
  public function testSecurity(): void {
    $php = new $this->storageClass($this->settings);
    $name = 'test.php';
    $php->save($name, '<?php');
    $expected_root_directory = $this->directory . '/test';
    if (str_ends_with($name, '.php')) {
      $expected_directory = $expected_root_directory . '/' . substr($name, 0, -4);
    }
    else {
      $expected_directory = $expected_root_directory . '/' . $name;
    }
    $directory_mtime = filemtime($expected_directory);
    $expected_filename = $expected_directory . '/' . Crypt::hmacBase64($name, $this->secret . $directory_mtime) . '.php';

    // Ensure the file exists and that it and the containing directory have
    // minimal permissions. fileperms() can return high bits unrelated to
    // permissions, so mask with 0777.
    $this->assertFileExists($expected_filename);
    $this->assertSame(0444, fileperms($expected_filename) & 0777);
    $this->assertSame(0777, fileperms($expected_directory) & 0777);

    // Ensure the root directory for the bin has a .htaccess file denying web
    // access.
    $this->assertSame(file_get_contents($expected_root_directory . '/.htaccess'), FileSecurity::htaccessLines());

    // Ensure that if the file is replaced with an untrusted one (due to another
    // script's file upload vulnerability), it does not get loaded. Since mtime
    // granularity is 1 second, we cannot prevent an attack that happens within
    // a second of the initial save().
    sleep(1);
    for ($i = 0; $i < 2; $i++) {
      $php = new $this->storageClass($this->settings);
      $GLOBALS['hacked'] = FALSE;
      $untrusted_code = "<?php\n" . '$GLOBALS["hacked"] = TRUE;';
      chmod($expected_directory, 0700);
      chmod($expected_filename, 0700);
      if ($i) {
        // Now try to write the file in such a way that the directory mtime
        // changes and invalidates the hash.
        file_put_contents($expected_filename . '.tmp', $untrusted_code);
        rename($expected_filename . '.tmp', $expected_filename);
      }
      else {
        // On the first try do not change the directory mtime but the filemtime
        // is now larger than the directory mtime.
        file_put_contents($expected_filename, $untrusted_code);
      }
      chmod($expected_filename, 0400);
      chmod($expected_directory, 0100);
      $this->assertSame(file_get_contents($expected_filename), $untrusted_code);
      $this->assertSame($this->expected[$i], $php->exists($name));
      $this->assertSame($this->expected[$i], $php->load($name));
      $this->assertSame($this->expected[$i], $GLOBALS['hacked']);
    }
    unset($GLOBALS['hacked']);
  }

  /**
   * Tests that checkFile handles race conditions without PHP warnings.
   *
   * This test simulates a race condition where a file is deleted between
   * the file_exists() check and the filemtime() call in checkFile().
   *
   * The race condition is simulated by mocking filemtime() to return FALSE
   * (simulating the file being deleted after file_exists() returned TRUE).
   *
   * Without the fix, this test will:
   * - Trigger a PHP warning: "filemtime(): stat failed for ..."
   * - Return TRUE incorrectly (due to PHP type coercion: false <= int = true)
   *
   * With the fix (@filemtime with !== FALSE check), this test will:
   * - Not trigger any PHP warning
   * - Return FALSE correctly
   *
   * @see https://www.drupal.org/project/drupal/issues/3557344
   */
  public function testRaceConditionFilemtimeWarning(): void {
    // Use mock class that can simulate race condition.
    $php = new MTimeProtectedFileStorageMock($this->settings);
    $name = 'race_condition_test.php';

    // Save a test file.
    $this->assertTrue($php->save($name, '<?php return TRUE;'));

    // Verify normal operation works (without mock).
    $this->assertTrue($php->exists($name), 'File exists before race condition');

    // Enable filemtime() failure simulation.
    // This simulates the race condition where file is deleted after
    // file_exists() returns TRUE but before filemtime() is called.
    self::$simulateFilemtimeFailure = TRUE;

    // This should return FALSE when filemtime() fails.
    // Without the fix: triggers warning and returns TRUE (bug).
    // With the fix: no warning and returns FALSE (correct).
    $result = $php->exists($name);

    // Reset mock.
    self::$simulateFilemtimeFailure = FALSE;

    // The correct behavior is to return FALSE when filemtime() fails.
    $this->assertFalse($result, 'exists() returns FALSE when filemtime() fails during race condition');

    // Cleanup.
    $php->delete($name);
  }

}
