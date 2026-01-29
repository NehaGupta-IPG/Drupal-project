<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library\Unit;

use Drupal\media\MediaInterface;
use Drupal\media_library\Hook\MediaLibraryThemeHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the media library theme hooks.
 */
#[CoversClass(MediaLibraryThemeHooks::class)]
#[Group('media_library')]
class MediaLibraryThemeHooksTest extends UnitTestCase {

  /**
   * Tests that the edit form URL is used when update access is granted.
   */
  public function testPreprocessUpdate(): void {
    $media = $this->createMediaStub(TRUE);

    $variables = [
      'view_mode' => 'media_library',
      'media' => $media,
      '#cache' => [],
    ];

    (new MediaLibraryThemeHooks())->preprocessMedia($variables);

    // Assert the URL object created inside holds the correct relation.
    $this->assertSame('/__rel/edit-form', $variables['url']->toString());
    $this->assertContains('user.permissions', $variables['#cache']['contexts']);
  }

  /**
   * Tests that the canonical URL is used when update access is forbidden.
   */
  public function testPreprocessCannotUpdate(): void {
    $media = $this->createMediaStub(FALSE);

    $variables = [
      'view_mode' => 'media_library',
      'media' => $media,
      '#cache' => [],
    ];

    (new MediaLibraryThemeHooks())->preprocessMedia($variables);

    $this->assertSame('/__rel/canonical', $variables['url']->toString());
    $this->assertContains('user.permissions', $variables['#cache']['contexts']);
  }

  /**
   * Creates a stub media entity with specific access result.
   *
   * @param bool $can_update
   *   Whether the media item allows the 'update' operation.
   *
   * @return \Drupal\media\MediaInterface
   *   The stubbed media entity.
   */
  private function createMediaStub(bool $can_update): MediaInterface {
    $media = $this->createStub(MediaInterface::class);

    $media->method('access')
      ->willReturn($can_update);

    $media->method('toUrl')
      ->willReturnCallback(function (string $rel, array $options = []) {
        return new class($rel) {

          /**
           * Constructs the anonymous URL class.
           */
          public function __construct(private string $rel) {}

          /**
           * Returns the string representation of the URL.
           */
          public function toString(): string {
            return '/__rel/' . $this->rel;
          }

        };
      });

    return $media;
  }

}
