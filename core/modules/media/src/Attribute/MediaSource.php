<?php

declare(strict_types=1);

namespace Drupal\media\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a MediaSource attribute.
 *
 * Media sources are responsible for implementing all the logic for dealing
 * with a particular type of media. They provide various universal and
 * type-specific metadata about media of the type they handle.
 *
 * Plugin namespace: Plugin\media\Source
 *
 * For a working example, see \Drupal\media\Plugin\media\Source\File.
 *
 * @see \Drupal\media\MediaSourceInterface
 * @see \Drupal\media\MediaSourceBase
 * @see \Drupal\media\MediaSourceManager
 * @see hook_media_source_info_alter()
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class MediaSource extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly array $allowed_field_types = [],
    public readonly array $forms = [],
    public readonly string $default_thumbnail_filename = 'generic.png',
    public readonly string $thumbnail_uri_metadata_attribute = 'thumbnail_uri',
    public readonly string $thumbnail_width_metadata_attribute = 'thumbnail_width',
    public readonly string $thumbnail_height_metadata_attribute = 'thumbnail_height',
    public readonly ?string $thumbnail_alt_metadata_attribute = NULL,
    public readonly ?string $thumbnail_title_metadata_attribute = NULL,
    public readonly string $default_name_metadata_attribute = 'default_name',
    public readonly ?string $deriver = NULL,
  ) {}

}
