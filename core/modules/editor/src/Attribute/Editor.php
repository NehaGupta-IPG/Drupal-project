<?php

namespace Drupal\editor\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an Editor attribute object.
 *
 * Plugin Namespace: Plugin\Editor
 *
 * For a working example, see \Drupal\ckeditor5\Plugin\Editor\CKEditor5
 *
 * @see \Drupal\editor\Plugin\EditorPluginInterface
 * @see \Drupal\editor\Plugin\EditorBase
 * @see \Drupal\editor\Plugin\EditorManager
 * @see hook_editor_info_alter()
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Editor extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly bool $supports_content_filtering,
    public readonly bool $supports_inline_editing,
    public readonly bool $is_xss_safe,
    public readonly array $supported_element_types,
    public readonly ?string $deriver = NULL,
  ) {}

}
