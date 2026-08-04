<?php

declare(strict_types=1);

namespace Drupal\Tests\filter\Unit;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Image\ImageInterface;
use Drupal\filter\Plugin\Filter\FilterHtmlImageSecure;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the private image helpers of the secure image filter.
 */
#[CoversClass(FilterHtmlImageSecure::class)]
#[Group('filter')]
final class FilterHtmlImageSecureTest extends UnitTestCase {

  /**
   * Tests isPrivateImage().
   *
   * @param string $src
   *   The image source to check.
   * @param string $private_path
   *   The private path prefix.
   * @param bool $expected
   *   Whether the source is expected to be identified as private.
   */
  #[DataProvider('providerIsPrivateImage')]
  public function testIsPrivateImage(string $src, string $private_path, bool $expected): void {
    $filter = $this->createFilter();
    $method = new \ReflectionMethod(FilterHtmlImageSecure::class, 'isPrivateImage');
    $this->assertSame(
      $expected,
      $method->invoke($filter, $src, $private_path, mb_strlen($private_path)),
    );
  }

  /**
   * Provides data for testIsPrivateImage().
   *
   * @return array
   *   An array of test data.
   */
  public static function providerIsPrivateImage(): array {
    return [
      'private path at root' => [
        'src' => '/system/files/image.png',
        'private_path' => '/system/files/',
        'expected' => TRUE,
      ],
      'private path with subdirectory base_path' => [
        'src' => '/subdir/system/files/image.png',
        'private_path' => '/subdir/system/files/',
        'expected' => TRUE,
      ],
      'private path with query string' => [
        'src' => '/system/files/image.png?itok=abc123',
        'private_path' => '/system/files/',
        'expected' => TRUE,
      ],
      'private path nested directory' => [
        'src' => '/system/files/styles/thumbnail/image.png',
        'private_path' => '/system/files/',
        'expected' => TRUE,
      ],
      'public path is not private' => [
        'src' => '/sites/default/files/image.png',
        'private_path' => '/system/files/',
        'expected' => FALSE,
      ],
      'external url is not private' => [
        'src' => 'http://example.com/system/files/image.png',
        'private_path' => '/system/files/',
        'expected' => FALSE,
      ],
      'partial prefix match is not private' => [
        'src' => '/system/files-public/image.png',
        'private_path' => '/system/files/',
        'expected' => FALSE,
      ],
      'private path prefix not at start' => [
        'src' => '/other/system/files/image.png',
        'private_path' => '/system/files/',
        'expected' => FALSE,
      ],
      'empty source' => [
        'src' => '',
        'private_path' => '/system/files/',
        'expected' => FALSE,
      ],
    ];
  }

  /**
   * Tests isSecurePrivateImage() with a valid, existing image.
   */
  public function testIsSecurePrivateImageValid(): void {
    $image = $this->createMock(ImageInterface::class);
    $image->expects($this->once())
      ->method('getFileSize')
      ->willReturn(12345);

    $image_factory = $this->createMock(ImageFactory::class);
    $image_factory->expects($this->once())
      ->method('get')
      ->with('private://image.png')
      ->willReturn($image);

    $this->assertTrue(
      $this->invokeIsSecurePrivateImage($image_factory, '/system/files/image.png'),
    );
  }

  /**
   * Tests isSecurePrivateImage() strips the query string before resolving.
   */
  public function testIsSecurePrivateImageStripsQueryString(): void {
    $image = $this->createMock(ImageInterface::class);
    $image->method('getFileSize')->willReturn(12345);

    $image_factory = $this->createMock(ImageFactory::class);
    $image_factory->expects($this->once())
      ->method('get')
      ->with('private://image.png')
      ->willReturn($image);

    $this->assertTrue(
      $this->invokeIsSecurePrivateImage($image_factory, '/system/files/image.png?itok=abc123'),
    );
  }

  /**
   * Tests isSecurePrivateImage() rawurldecodes the path before resolving.
   */
  public function testIsSecurePrivateImageDecodesPath(): void {
    $image = $this->createMock(ImageInterface::class);
    $image->method('getFileSize')->willReturn(12345);

    $image_factory = $this->createMock(ImageFactory::class);
    $image_factory->expects($this->once())
      ->method('get')
      ->with('private://tést fïle.png')
      ->willReturn($image);

    $this->assertTrue(
      $this->invokeIsSecurePrivateImage($image_factory, '/system/files/t%C3%A9st%20f%C3%AFle.png'),
    );
  }

  /**
   * Tests isSecurePrivateImage() with a missing or invalid image file.
   *
   * @param int|null $file_size
   *   The file size returned by the image object, or NULL when the file cannot
   *   be resolved as an image.
   */
  #[DataProvider('providerIsSecurePrivateImageInvalid')]
  public function testIsSecurePrivateImageInvalid(?int $file_size): void {
    $image = $this->createMock(ImageInterface::class);
    $image->method('getFileSize')->willReturn($file_size);

    $image_factory = $this->createMock(ImageFactory::class);
    $image_factory->expects($this->once())
      ->method('get')
      ->with('private://missing.png')
      ->willReturn($image);

    $this->assertFalse(
      $this->invokeIsSecurePrivateImage($image_factory, '/system/files/missing.png'),
    );
  }

  /**
   * Provides data for testIsSecurePrivateImageInvalid().
   *
   * @return array
   *   An array of test data.
   */
  public static function providerIsSecurePrivateImageInvalid(): array {
    return [
      'file does not resolve as image' => ['file_size' => NULL],
      'zero-byte file' => ['file_size' => 0],
    ];
  }

  /**
   * Invokes the protected isSecurePrivateImage() method.
   *
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory to inject.
   * @param string $src
   *   The image source to check.
   * @param string $private_path
   *   The private path prefix.
   *
   * @return bool
   *   The result of the method call.
   */
  protected function invokeIsSecurePrivateImage(ImageFactory $image_factory, string $src, string $private_path = '/system/files/'): bool {
    $filter = $this->createFilter($image_factory);
    $method = new \ReflectionMethod(FilterHtmlImageSecure::class, 'isSecurePrivateImage');
    return $method->invoke($filter, $src, $private_path, mb_strlen($private_path));
  }

  /**
   * Creates a filter instance with mocked dependencies.
   *
   * @param \Drupal\Core\Image\ImageFactory|null $image_factory
   *   The image factory to inject, or NULL to use a stub.
   *
   * @return \Drupal\filter\Plugin\Filter\FilterHtmlImageSecure
   *   The filter instance.
   */
  protected function createFilter(?ImageFactory $image_factory = NULL): FilterHtmlImageSecure {
    return new FilterHtmlImageSecure(
      [],
      'filter_html_image_secure',
      ['provider' => 'test'],
      $this->createMock(FileUrlGeneratorInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
      '/app/root',
      $image_factory ?? $this->createMock(ImageFactory::class),
    );
  }

}
