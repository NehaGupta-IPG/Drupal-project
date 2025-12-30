<?php

declare(strict_types=1);

namespace Drupal\help\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a HelpSection attribute object for plugin discovery.
 *
 * Plugin Namespace: Plugin\HelpSection
 *
 * For a working example, see \Drupal\help\Plugin\HelpSection\HookHelpSection.
 *
 * @see \Drupal\help\HelpSectionPluginInterface
 * @see \Drupal\help\Plugin\HelpSection\HelpSectionPluginBase
 * @see \Drupal\help\HelpSectionManager
 * @see hook_help_section_info_alter()
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class HelpSection extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $title,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $permission = NULL,
    public readonly ?int $weight = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
