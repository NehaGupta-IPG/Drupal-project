<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Traits;

use Drupal\media\Entity\MediaType;

/**
 * Provides methods to create a media type from given values.
 *
 * This trait is meant to be used only by test classes.
 */
trait MediaTypeCreationTrait {

  /**
   * Create a media type for a source plugin.
   *
   * @param string $source_plugin_id
   *   The media source plugin ID.
   * @param mixed[] $values
   *   (optional) Additional values for the media type entity:
   *   - id: The ID of the media type. If none is provided, a random value will
   *     be used.
   *   - label: The human-readable label of the media type. If none is provided,
   *     a random value will be used.
   *   See \Drupal\media\MediaTypeInterface and \Drupal\media\Entity\MediaType
   *   for full documentation of the media type properties.
   *
   * @return \Drupal\media\MediaTypeInterface
   *   A media type.
   *
   * @see \Drupal\media\MediaTypeInterface
   * @see \Drupal\media\Entity\MediaType
   */
  protected function createMediaType($source_plugin_id, array $values = []) {
    $values += [
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'source' => $source_plugin_id,
    ];

    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = MediaType::create($values);
    $this->assertSame(SAVED_NEW, $media_type->save());

    return $media_type;
  }

}
