<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Action\Attribute;

// cspell:ignore inflector
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Config\Action\Exists;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * @internal
 *   This API is experimental.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class ActionMethod {

  public function __construct(
    public readonly Exists $exists = Exists::ErrorIfNotExists,
    public readonly TranslatableMarkup|string $adminLabel = '',
    public readonly bool|string $pluralize = TRUE,
    public readonly ?string $name = NULL,
  ) {
    if ($name && !preg_match(ExtensionDiscovery::PHP_FUNCTION_PATTERN, $name)) {
      throw new InvalidPluginDefinitionException('entity_method', sprintf("'%s' is not a valid PHP function name.", $name));
    }
  }

}
