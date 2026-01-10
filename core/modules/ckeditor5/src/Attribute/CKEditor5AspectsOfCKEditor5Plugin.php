<?php

declare(strict_types = 1);

namespace Drupal\ckeditor5\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Defines the CKEditor5 aspect of CKEditor5 plugin.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CKEditor5AspectsOfCKEditor5Plugin extends Plugin {

  public function __construct(
    public readonly array $plugins,
    public readonly array $config = [],
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get(): array|object {
    return [
      'plugins' => $this->plugins,
      'config' => $this->config,
    ];
  }

}
