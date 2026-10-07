<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Kernel;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageStyleInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests derivatives of image styles that change the extension.
 */
#[Group('image')]
#[RunTestsInSeparateProcesses]
class ImageStyleConvertedExtensionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'image', 'image_module_test'];

  /**
   * An image style that converts images to GIF.
   */
  protected ImageStyleInterface $imageStyle;

  /**
   * The file system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->config('system.file')->set('default_scheme', 'public')->save();
    $this->fileSystem = $this->container->get('file_system');

    $this->imageStyle = ImageStyle::create([
      'name' => 'test',
      'label' => 'Test',
    ]);
    $this->imageStyle->addImageEffect([
      'id' => 'image_convert',
      'weight' => 0,
      'data' => [
        'extension' => 'gif',
      ],
    ]);
    $this->imageStyle->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpFilesystem(): void {
    parent::setUpFilesystem();
    mkdir($this->siteDirectory . '/private', 0775);
    $this->setSetting('file_private_path', $this->siteDirectory . '/private');
  }

  /**
   * Tests that images which only differ in their extension don't collide.
   */
  public function testBuildUri(): void {
    $this->assertSame('public://styles/test/png/public/collision/foo.gif', $this->imageStyle->buildUri('public://collision/foo.png'));
    $this->assertSame('public://styles/test/jpg/public/collision/foo.gif', $this->imageStyle->buildUri('public://collision/foo.jpg'));
    // An image that already is a GIF is not converted, so it keeps the
    // existing derivative URI.
    $this->assertSame('public://styles/test/public/collision/foo.gif', $this->imageStyle->buildUri('public://collision/foo.gif'));
    // A source directory with the name of an extension can't collide with the
    // extension directory, because that is in front of the scheme.
    $this->assertSame('public://styles/test/public/png/collision/foo.gif', $this->imageStyle->buildUri('public://png/collision/foo.gif'));

    $this->assertNotSame($this->imageStyle->getPathToken('public://collision/foo.png'), $this->imageStyle->getPathToken('public://collision/foo.jpg'));
  }

  /**
   * Tests that the path processor extracts the source extension.
   */
  public function testPathProcessor(): void {
    $path_processor = $this->container->get('path_processor.image_styles');

    $request = Request::create('/system/files/styles/test/png/private/collision/foo.gif');
    $path = $path_processor->processInbound('/system/files/styles/test/png/private/collision/foo.gif', $request);
    $this->assertSame('/system/files/styles/test/private', $path);
    $this->assertSame('collision/foo.gif', $request->query->get('file'));
    $this->assertSame('png', $request->query->get('source_extension'));

    // A source directory with the name of an extension is part of the file.
    // A source extension passed in the query string is ignored.
    $request = Request::create('/system/files/styles/test/private/png/foo.gif', 'GET', ['source_extension' => 'jpg']);
    $path = $path_processor->processInbound('/system/files/styles/test/private/png/foo.gif', $request);
    $this->assertSame('/system/files/styles/test/private', $path);
    $this->assertSame('png/foo.gif', $request->query->get('file'));
    $this->assertNull($request->query->get('source_extension'));
  }

  /**
   * Tests that flushing an image also removes its legacy derivative.
   */
  public function testFlush(): void {
    $png_derivative_uri = $this->imageStyle->buildUri('public://collision/foo.png');
    $legacy_png_derivative_uri = 'public://styles/test/public/collision/foo.png.gif';
    $jpg_derivative_uri = $this->imageStyle->buildUri('public://collision/foo.jpg');
    foreach ([$png_derivative_uri, $legacy_png_derivative_uri, $jpg_derivative_uri] as $uri) {
      $this->createImage($uri, 'gif');
    }

    $this->imageStyle->flush('public://collision/foo.png');
    $this->assertFileDoesNotExist($png_derivative_uri);
    $this->assertFileDoesNotExist($legacy_png_derivative_uri);
    $this->assertFileExists($jpg_derivative_uri);
  }

  /**
   * Tests that access to a private derivative is checked on its own source.
   */
  public function testPrivateFileDownload(): void {
    $this->createImage('private://collision/foo.png', 'png');
    $this->createImage('private://collision/foo.jpg', 'jpg');
    $png_derivative_uri = $this->imageStyle->buildUri('private://collision/foo.png');
    $jpg_derivative_uri = $this->imageStyle->buildUri('private://collision/foo.jpg');
    $this->createImage($png_derivative_uri, 'gif');
    $this->createImage($jpg_derivative_uri, 'gif');

    // Only grant access to the JPEG source image.
    \Drupal::keyValue('image')->set('test_file_download', 'private://collision/foo.jpg');
    $module_handler = $this->container->get('module_handler');

    $headers = $module_handler->invokeAll('file_download', [$jpg_derivative_uri]);
    $this->assertNotContains(-1, $headers);
    $this->assertSame('image/gif', $headers['Content-Type']);

    $headers = $module_handler->invokeAll('file_download', [$png_derivative_uri]);
    $this->assertContains(-1, $headers);
  }

  /**
   * Copies a test image to the given URI.
   *
   * @param string $uri
   *   The destination URI.
   * @param string $extension
   *   The extension of the test image to copy.
   */
  protected function createImage(string $uri, string $extension): void {
    $directory = $this->fileSystem->dirname($uri);
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $this->fileSystem->copy("core/tests/fixtures/files/image-test.$extension", $uri, FileExists::Replace);
  }

}
