<?php

declare(strict_types=1);

namespace Drupal\Tests\ckeditor5\FunctionalJavascript;

/**
 * @coversDefaultClass \Drupal\ckeditor5\Plugin\CKEditor5Plugin\Image
 * @group ckeditor5
 * @group #slow
 * @internal
 */
class ImageTestProviderTest extends ImageTestTestBase {
  use ImageTestProviderTrait;

  /**
   * Provides the relevant image attributes.
   *
   * @return string[]
   *   Default image attributes for tests.
   */
  protected function imageAttributes(): array {
    return [
      'data-entity-type' => 'file',
      'data-entity-uuid' => $this->file->uuid(),
      'src' => $this->file->createFileUrl(),
      'height' => '20',
    ];
  }

}
