<?php

declare(strict_types = 1);

namespace Drupal\ckeditor5\Attribute;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Define a Drupal aspects of CKEditor5 plugin.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class DrupalAspectsOfCKEditor5Plugin extends Plugin {

  public function __construct(
    public readonly string|TranslatableMarkup|null $label = NULL,
    public string $class = CKEditor5PluginDefault::class,
    public readonly string|false $library = FALSE,
    public readonly string|false $admin_library = FALSE,
    public readonly array|false|null $elements = NULL,
    public readonly array $toolbar_items = [],
    public readonly array|false $conditions = FALSE,
    public readonly ?string $deriver = NULL,
  ) {}

}
