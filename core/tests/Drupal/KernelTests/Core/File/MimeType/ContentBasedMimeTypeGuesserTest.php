<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\File\MimeType;

use Drupal\Core\File\MimeType\ContentBasedMimeTypeGuesser;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the content-based MIME type guesser.
 */
#[Group('File')]
#[CoversClass(ContentBasedMimeTypeGuesser::class)]
#[RunTestsInSeparateProcesses]
class ContentBasedMimeTypeGuesserTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['file_test'];

  /**
   * {@inheritdoc}
   */
  protected $container;

  /**
   * Tests MIME detection on a file with no extension via content inspection.
   */
  public function testGuessMimeTypeFromContents(): void {
    $guesser = $this->container->get('file.mime_type.guesser');

    // PDF magic bytes, no file extension.
    $pdf_path = $this->createFileWithoutExtension("%PDF-1.4\n%\xE2\xE3\xCF\xD3\n");
    $this->assertSame('application/pdf', $guesser->guessMimeType($pdf_path));

    // JPEG magic bytes, no file extension.
    $png_path = $this->createFileWithoutExtension("\xFF\xD8\xFF\xE1");
    $this->assertSame('image/jpeg', $guesser->guessMimeType($png_path));

    // PNG magic bytes, no file extension.
    $png_path = $this->createFileWithoutExtension(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
    $this->assertSame('image/png', $guesser->guessMimeType($png_path));

    // Plain text content, no extension.
    $txt_path = $this->createFileWithoutExtension("Hello world.\n");
    $this->assertSame('text/plain', $guesser->guessMimeType($txt_path));
  }

  /**
   * Tests that a non-existent path returns NULL gracefully.
   */
  public function testGuessMimeTypeOnMissingFile(): void {
    $guesser = $this->container->get('file.mime_type.guesser.content');
    $this->assertNull($guesser->guessMimeType('/tmp/definitely-does-not-exist-' . uniqid()));
  }

  /**
   * Creates a temporary file without extension and returns its path.
   */
  private function createFileWithoutExtension(string $contents): string {
    $path = tempnam(sys_get_temp_dir(), 'mime_');
    file_put_contents($path, $contents);
    return $path;
  }

}
