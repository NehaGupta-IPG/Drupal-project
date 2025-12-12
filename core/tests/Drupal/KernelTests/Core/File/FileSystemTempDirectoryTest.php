<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\File;

use Drupal\Component\FileSystem\FileSystem as FileSystemComponent;
use Drupal\Core\File\FileSystem;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for getTempDirectory on FileSystem.
 */
#[CoversClass(\Drupal\Core\File\FileSystem::class)]
#[Group('File')]
#[RunTestsInSeparateProcesses]
class FileSystemTempDirectoryTest extends KernelTestBase {

  /**
   * The file system under test.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $stream_wrapper_manager = $this->container->get('stream_wrapper_manager');
    $settings = $this->container->get('settings');
    $this->fileSystem = new FileSystem($stream_wrapper_manager, $settings);
  }

  /**
   * Tests 'file_temp_path' setting.
   */
  public function testGetTempDirectorySettings(): void {
    $tempDir = '/var/tmp/' . $this->randomMachineName();
    $this->setSetting('file_temp_path', $tempDir);
    $this->assertEquals($tempDir, $this->fileSystem->getTempDirectory());
  }

  /**
   * Tests os default fallback.
   */
  public function testGetTempDirectoryOsDefault(): void {
    $tempDir = FileSystemComponent::getOsTemporaryDirectory();
    $dir = $this->fileSystem->getTempDirectory();
    $this->assertEquals($tempDir, $dir);
  }

  /**
   * Tests FileSystem::tempnam().
   */
  public function testTempnam(): void {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $default_scheme = \Drupal::config('system.file')->get('default_scheme');
    $temporary_scheme = 'temporary';

    // Create a tmp file at the root of the default stream wrapper.
    $tempnam = $file_system->tempnam($default_scheme . '://', 'tmp');
    $this->assertNotEmpty($tempnam, 'Default stream wrapper can create temporary files');
    $this->assertEquals($default_scheme . '://tmp', substr($tempnam, 0, strlen($default_scheme . '://tmp')), 'Temporary file was created in the root of the default stream wrapper');

    // Create a tmp file in a subdirectory of the default stream wrapper.
    $directory = $this->createDirectory();
    $tempnam = $file_system->tempnam($directory, 'tmp');
    $this->assertNotEmpty($tempnam, 'Default stream wrapper can create temporary files');
    $this->assertEquals($directory, $file_system->dirname($tempnam), 'Temporary file was created in the default stream wrapper subdirectory');

    // Create a tmp file in a non-existing subdirectory of the default stream wrapper.
    $non_existing_directory = $default_scheme . '://' . $this->randomMachineName();
    $tempnam = @$file_system->tempnam($non_existing_directory, 'tmp');
    $this->assertFalse($tempnam, 'Default stream wrapper did not create a temporary file in missing directory');
    $this->assertNotEquals($non_existing_directory, $file_system->dirname($tempnam), 'Temporary file was not created in the default stream wrapper subdirectory');
    $this->assertNotEquals($default_scheme . '://', $file_system->dirname($tempnam), 'Temporary file was not created in the root of the default stream wrapper');

    // Create a tmp file at the root of the default stream wrapper.
    $tempnam = $file_system->tempnam($temporary_scheme . '://', 'tmp');
    $this->assertNotEmpty($tempnam, 'Temporary stream wrapper can create temporary files');
    $this->assertEquals($temporary_scheme . '://tmp', substr($tempnam, 0, strlen($temporary_scheme . '://tmp')), 'Temporary file was created in the root of the temporary stream wrapper');

    // Create a tmp file in a non-existing subdirectory of the temporary stream wrapper.
    $non_existing_directory = $temporary_scheme . '://' . $this->randomMachineName();
    $tempnam = @$file_system->tempnam($non_existing_directory, 'tmp');
    $this->assertNotEmpty($tempnam, 'Temporary stream wrapper can create temporary files');
    $this->assertNotEquals($non_existing_directory, $file_system->dirname($tempnam), 'Temporary file was not created in the temporary stream wrapper subdirectory');
    $this->assertEquals($temporary_scheme . '://', $file_system->dirname($tempnam), 'Temporary file was created in the root of the temporary stream wrapper, as fallback');
  }

}
