<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\File;

use Drupal\Core\DrupalKernel;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\PublicStream;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests stream wrapper functions.
 */
#[Group('File')]
#[RunTestsInSeparateProcesses]
class StreamWrapperTest extends FileTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['file_test'];

  /**
   * A stream wrapper scheme to register for the test.
   *
   * @var string
   */
  protected $scheme = 'dummy';

  /**
   * A fully-qualified stream wrapper class name to register for the test.
   *
   * @var string
   */
  protected $classname = 'Drupal\file_test\StreamWrapper\DummyStreamWrapper';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Add file_private_path setting.
    $request = Request::create('/');
    $site_path = DrupalKernel::findSitePath($request);
    $this->setSetting('file_private_path', $site_path . '/private');
  }

  /**
   * Tests the getClassName() function.
   */
  public function testGetClassName(): void {
    // Check the dummy scheme.
    $this->assertEquals($this->classname, \Drupal::service('stream_wrapper_manager')->getClass($this->scheme), 'Got correct class name for dummy scheme.');
    // Check core's scheme.
    $this->assertEquals('Drupal\Core\StreamWrapper\PublicStream', \Drupal::service('stream_wrapper_manager')->getClass('public'), 'Got correct class name for public scheme.');
  }

  /**
   * Tests the getViaScheme() method.
   */
  public function testGetInstanceByScheme(): void {
    $instance = \Drupal::service('stream_wrapper_manager')->getViaScheme($this->scheme);
    $this->assertEquals($this->classname, get_class($instance), 'Got correct class type for dummy scheme.');

    $instance = \Drupal::service('stream_wrapper_manager')->getViaScheme('public');
    $this->assertEquals('Drupal\Core\StreamWrapper\PublicStream', get_class($instance), 'Got correct class type for public scheme.');
  }

  /**
   * Tests the getViaUri() and getViaScheme() methods and target functions.
   */
  public function testUriFunctions(): void {
    /** @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager */
    $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager');

    $instance = $stream_wrapper_manager->getViaUri($this->scheme . '://foo');
    $this->assertEquals($this->classname, get_class($instance), 'Got correct class type for dummy URI.');

    $instance = $stream_wrapper_manager->getViaUri('public://foo');
    $this->assertEquals('Drupal\Core\StreamWrapper\PublicStream', get_class($instance), 'Got correct class type for public URI.');

    // Test file_uri_target().
    $this->assertEquals('foo/bar.txt', $stream_wrapper_manager::getTarget('public://foo/bar.txt'), 'Got a valid stream target from public://foo/bar.txt.');
    $this->assertEquals('image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==', $stream_wrapper_manager::getTarget('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg=='));
    $this->assertFalse($stream_wrapper_manager::getTarget('foo/bar.txt'), 'foo/bar.txt is not a valid stream.');
    $this->assertSame($stream_wrapper_manager::getTarget('public://'), '');
    $this->assertSame($stream_wrapper_manager::getTarget('data:'), '');

    // Test Drupal\Core\StreamWrapper\LocalStream::getDirectoryPath().
    $this->assertEquals(PublicStream::basePath(), $stream_wrapper_manager->getViaScheme('public')->getDirectoryPath(), 'Expected default directory path was returned.');
    $file_system = \Drupal::service('file_system');
    assert($file_system instanceof FileSystemInterface);
    $this->assertEquals($file_system->getTempDirectory(), $stream_wrapper_manager->getViaScheme('temporary')->getDirectoryPath(), 'Expected temporary directory path was returned.');

    // Test FileUrlGeneratorInterface::generateString()
    // TemporaryStream::getExternalUrl() uses Url::fromRoute(), which needs
    // route information to work.
    $file_url_generator = $this->container->get('file_url_generator');
    assert($file_url_generator instanceof FileUrlGeneratorInterface);
    $this->assertStringContainsString('system/temporary?file=test.txt', $file_url_generator->generateString('temporary://test.txt'), 'Temporary external URL correctly built.');
    $this->assertStringContainsString(Settings::get('file_public_path') . '/test.txt', $file_url_generator->generateString('public://test.txt'), 'Public external URL correctly built.');
    $this->assertStringContainsString('system/files/test.txt', $file_url_generator->generateString('private://test.txt'), 'Private external URL correctly built.');
  }

  /**
   * Tests some file handle functions.
   */
  public function testFileFunctions(): void {
    $filename = 'public://' . $this->randomMachineName();
    file_put_contents($filename, str_repeat('d', 1000));

    // Open for rw and place pointer at beginning of file so select will return.
    $handle = fopen($filename, 'c+');
    $this->assertNotFalse($handle, 'Able to open a file for appending, reading and writing.');

    // Attempt to change options on the file stream: should all fail.
    $this->assertFalse(@stream_set_blocking($handle, FALSE), 'Unable to set to non blocking using a local stream wrapper.');
    $this->assertFalse(@stream_set_blocking($handle, TRUE), 'Unable to set to blocking using a local stream wrapper.');
    $this->assertFalse(@stream_set_timeout($handle, 1), 'Unable to set read time out using a local stream wrapper.');
    $this->assertEquals(-1 /*EOF*/, @stream_set_write_buffer($handle, 512), 'Unable to set write buffer using a local stream wrapper.');

    // This will test stream_cast().
    $read = [$handle];
    $write = NULL;
    $except = NULL;
    $this->assertEquals(1, stream_select($read, $write, $except, 0), 'Able to cast a stream via stream_select.');

    // This will test stream_truncate().
    $this->assertEquals(1, ftruncate($handle, 0), 'Able to truncate a stream via ftruncate().');
    fclose($handle);
    $this->assertEquals(0, filesize($filename), 'Able to truncate a stream.');

    // Cleanup.
    unlink($filename);
  }

  /**
   * Tests the scheme functions.
   */
  public function testGetValidStreamScheme(): void {

    /** @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager */
    $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager');

    $this->assertEquals('foo', $stream_wrapper_manager::getScheme('foo://pork//chops'), 'Got the correct scheme from foo://asdf');
    $this->assertEquals('data', $stream_wrapper_manager::getScheme('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg=='), 'Got the correct scheme from a data URI.');
    $this->assertFalse($stream_wrapper_manager::getScheme('foo/bar.txt'), 'foo/bar.txt is not a valid stream.');
    $this->assertTrue($stream_wrapper_manager->isValidScheme($stream_wrapper_manager::getScheme('public://asdf')), 'Got a valid stream scheme from public://asdf');
    $this->assertFalse($stream_wrapper_manager->isValidScheme($stream_wrapper_manager::getScheme('foo://asdf')), 'Did not get a valid stream scheme from foo://asdf');
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
