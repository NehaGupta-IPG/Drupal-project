<?php

namespace Drupal\language\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a language negotiation attribute object.
 *
 * Plugin Namespace: Plugin\LanguageNegotiation
 *
 * For a working example, see
 * \Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationBrowser.
 *
 * @see \Drupal\language\LanguageNegotiator
 * @see \Drupal\language\LanguageNegotiationMethodManager
 * @see \Drupal\language\LanguageNegotiationMethodInterface
 * @see hook_language_negotiation_info_alter()
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class LanguageNegotiation extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $name,
    public readonly ?array $types = NULL,
    public readonly int $weight = 0,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $config_route_name = NULL,
  ) {}

}
