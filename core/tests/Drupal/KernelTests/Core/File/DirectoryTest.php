<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\File;

use Drupal\Component\FileSecurity\FileSecurity;
use Drupal\Component\FileSystem\FileSystem;
use Drupal\Core\Database\Database;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\HtaccessWriterInterface;
use Drupal\Tests\AutowireProperty;
use Drupal\system\Hook\SystemHooks;

/**
 * Tests operations dealing with directories.
 *
 * @group File
 */
class DirectoryTest extends FileTestBase {

  /**
   * Site path.
   */
  #[AutowireProperty(param: 'site.path')]
  protected string $sitePath;

  /**
   * File system service.
   */
  #[AutowireProperty]
  protected FileSystemInterface $fileSystem;

  /**
   * Htaccess writer service.
   */
  #[AutowireProperty]
  protected HtaccessWriterInterface $htaccessWriter;

  /**
   * Tests local directory handling functions.
   */
  public function testFileCheckLocalDirectoryHandling(): void {
    $directory = $this->sitePath . '/files';

    // Check a new recursively created local directory for correct file system
    // permissions.
    $parent = $this->randomMachineName();
    $child = $this->randomMachineName();

    // Files directory already exists.
    $this->assertDirectoryExists($directory);
    // Make files directory writable only.
    $old_mode = fileperms($directory);

    // Create the directories.
    $parent_path = $directory . DIRECTORY_SEPARATOR . $parent;
    $child_path = $parent_path . DIRECTORY_SEPARATOR . $child;
    $this->assertTrue($this->fileSystem->mkdir($child_path, 0775, TRUE), 'No error reported when creating new local directories.');

    // Ensure new directories also exist.
    $this->assertDirectoryExists($parent_path);
    $this->assertDirectoryExists($child_path);

    // Check that new directory permissions were set properly.
    $this->assertDirectoryPermissions($parent_path, 0775);
    $this->assertDirectoryPermissions($child_path, 0775);

    // Check that existing directory permissions were not modified.
    $this->assertDirectoryPermissions($directory, $old_mode);

    // Check creating a directory using an absolute path.
    $absolute_path = $this->fileSystem->realpath($directory) . DIRECTORY_SEPARATOR . $this->randomMachineName() . DIRECTORY_SEPARATOR . $this->randomMachineName();
    $this->assertTrue($this->fileSystem->mkdir($absolute_path, 0775, TRUE), 'No error reported when creating new absolute directories.');
    $this->assertDirectoryPermissions($absolute_path, 0775);
  }

  /**
   * Tests directory handling functions.
   */
  public function testFileCheckDirectoryHandling(): void {
    // A directory to operate on.
    $default_scheme = 'public';
    $directory = $default_scheme . '://' . $this->randomMachineName() . '/' . $this->randomMachineName();
    $this->assertDirectoryDoesNotExist($directory);

    // Non-existent directory.
    $this->assertFalse($this->fileSystem->prepareDirectory($directory, 0), 'Error reported for non-existing directory.');

    // Make a directory.
    $this->assertTrue($this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY), 'No error reported when creating a new directory.');

    // Make sure directory actually exists.
    $this->assertDirectoryExists($directory);
    if (!str_starts_with(PHP_OS, 'WIN')) {
      // PHP on Windows doesn't support any kind of useful read-only mode for
      // directories. When executing a chmod() on a directory, PHP only sets the
      // read-only flag, which doesn't prevent files to actually be written
      // in the directory on any recent version of Windows.

      // Make directory read only.
      @$this->fileSystem->chmod($directory, 0444);
      $this->assertFalse($this->fileSystem->prepareDirectory($directory, 0), 'Error reported for a non-writable directory.');

      // Test directory permission modification.
      $this->setSetting('file_chmod_directory', 0777);
      $this->assertTrue($this->fileSystem->prepareDirectory($directory, FileSystemInterface::MODIFY_PERMISSIONS), 'No error reported when making directory writable.');
    }

    // Test that the directory has the correct permissions.
    $this->assertDirectoryPermissions($directory, 0777, 'file_chmod_directory setting is respected.');

    // Remove .htaccess file to then test that it gets re-created.
    @$this->fileSystem->unlink($default_scheme . '://.htaccess');
    $this->assertFileDoesNotExist($default_scheme . '://.htaccess');
    $this->htaccessWriter->ensure();
    $this->assertFileExists($default_scheme . '://.htaccess');

    // Remove .htaccess file again to test that it is re-created by a cron run.
    @$this->fileSystem->unlink($default_scheme . '://.htaccess');
    $this->assertFileDoesNotExist($default_scheme . '://.htaccess');
    $systemCron = new SystemHooks();
    $systemCron->cron();
    $this->assertFileExists($default_scheme . '://.htaccess');

    // Verify contents of .htaccess file.
    $file = file_get_contents($default_scheme . '://.htaccess');
    $this->assertEquals(FileSecurity::htaccessLines(FALSE), $file, 'The .htaccess file contains the proper content.');
  }

  /**
   * Tests the file paths of newly created files.
   */
  public function testFileCreateNewFilepath(): void {
    // First we test against an imaginary file that does not exist in a
    // directory.
    $basename = 'xyz.txt';
    $directory = 'core/misc';
    $original = $directory . '/' . $basename;
    $path = $this->fileSystem->createFilename($basename, $directory);
    $this->assertEquals($original, $path, "New filepath $path equals $original.");

    // Then we test against a file that already exists within that directory.
    $basename = 'druplicon.png';
    $original = $directory . '/' . $basename;
    $expected = $directory . '/druplicon_0.png';
    $path = $this->fileSystem->createFilename($basename, $directory);
    $this->assertEquals($expected, $path, "Creating a new filepath from $path equals $original (expected $expected).");

    // @todo Finally we copy a file into a directory several times, to ensure a properly iterating filename suffix.
  }

  /**
   * Tests the destination file path.
   *
   * This will test the filepath for a destination based on passed flags and
   * whether or not the file exists.
   *
   * If a file exists, ::getDestinationFilename($destination, $replace) will
   * either return:
   * - the existing filepath, if $replace is FileExists::Replace
   * - a new filepath if FileExists::Rename
   * - an error (returning FALSE) if FileExists::Error.
   * If the file doesn't currently exist, then it will simply return the
   * filepath.
   */
  public function testFileDestination(): void {
    // First test for non-existent file.
    $destination = 'core/misc/xyz.txt';
    $path = $this->fileSystem->getDestinationFilename($destination, FileExists::Replace);
    $this->assertEquals($destination, $path, 'Non-existing filepath destination is correct with FileExists::Replace.');
    $path = $this->fileSystem->getDestinationFilename($destination, FileExists::Rename);
    $this->assertEquals($destination, $path, 'Non-existing filepath destination is correct with FileExists::Rename.');
    $path = $this->fileSystem->getDestinationFilename($destination, FileExists::Error);
    $this->assertEquals($destination, $path, 'Non-existing filepath destination is correct with FileExists::Error.');

    $destination = 'core/misc/druplicon.png';
    $path = $this->fileSystem->getDestinationFilename($destination, FileExists::Replace);
    $this->assertEquals($destination, $path, 'Existing filepath destination remains the same with FileExists::Replace.');
    $path = $this->fileSystem->getDestinationFilename($destination, FileExists::Rename);
    $this->assertNotEquals($destination, $path, 'A new filepath destination is created when filepath destination already exists with FileExists::Rename.');
    $path = $this->fileSystem->getDestinationFilename($destination, FileExists::Error);
    $this->assertFalse($path, 'An error is returned when filepath destination already exists with FileExists::Error.');

    // Invalid UTF-8 causes an exception.
    $this->expectException(FileException::class);
    $this->expectExceptionMessage("Invalid filename 'a\xFFtest\x80€.txt'");
    $this->fileSystem->getDestinationFilename("core/misc/a\xFFtest\x80€.txt", FileExists::Replace);
  }

  /**
   * Ensure that the getTempDirectory() method always returns a value.
   */
  public function testFileDirectoryTemp(): void {
    $tmp_directory = $this->fileSystem->getTempDirectory();
    $this->assertNotEmpty($tmp_directory);
    $this->assertEquals($tmp_directory, FileSystem::getOsTemporaryDirectory());
  }

  /**
   * Tests directory creation.
   */
  public function testDirectoryCreation(): void {
    // mkdir() recursion should work with or without a trailing slash.
    $dir = $this->siteDirectory . '/files';
    $this->assertTrue($this->fileSystem->mkdir($dir . '/foo/bar', 0775, TRUE));
    $this->assertTrue($this->fileSystem->mkdir($dir . '/foo/baz/', 0775, TRUE));
  }

  /**
   * Tests asynchronous directory creation.
   *
   * Image style generation can result in many calls to create similar directory
   * paths. This test forks the process to create the same situation.
   */
  public function testMultiplePrepareDirectory(): void {
    if (!function_exists('pcntl_fork')) {
      $this->markTestSkipped('Requires the pcntl_fork() function');
    }
    $directories = [];
    for ($i = 1; $i <= 10; $i++) {
      $directories[] = 'public://a/b/c/d/e/f/g/h/' . $i;
    }

    $time_to_start = microtime(TRUE) + 0.1;
    // This loop creates a new fork to create each directory.
    foreach ($directories as $directory) {
      $pid = pcntl_fork();
      if ($pid == -1) {
        $this->fail("Error forking");
      }
      elseif ($pid == 0) {
        // Sleep so that all the forks start preparing the directory at the same
        // time.
        usleep((int) (($time_to_start - microtime(TRUE)) * 1000000));
        $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
        exit();
      }
    }

    // This while loop holds the parent process until all the child threads
    // are complete - at which point the script continues to execute.
    while (pcntl_waitpid(0, $status) != -1);

    foreach ($directories as $directory) {
      $this->assertDirectoryExists($directory);
    }

    // Remove the database connection because it will have been destroyed when
    // the forks exited. This allows
    // \Drupal\KernelTests\KernelTestBase::tearDown() to reopen it.
    Database::removeConnection('default');
  }

}
