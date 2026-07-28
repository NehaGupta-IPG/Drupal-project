<?php

declare(strict_types=1);

namespace Drupal\navigation\Attribute;

use Drupal\Component\Plugin\Attribute\PluginPropertyInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;

/**
 * Marks a block as available for placement in the navigation.
 *
 * Place this attribute on a block plugin class alongside the block attribute.
 * The 'allow_in_navigation' property is only set when the navigation module is
 * installed.
 *
 * For example:
 * @code
 * use Drupal\Core\Block\Attribute\Block;
 * use Drupal\Core\Block\BlockBase;
 * use Drupal\Core\StringTranslation\TranslatableMarkup;
 * use Drupal\navigation\Attribute\AllowInNavigation;
 *
 * #[Block(
 *   id: 'example',
 *   admin_label: new TranslatableMarkup('Example'),
 * )]
 * #[AllowInNavigation]
 * class ExampleBlock extends BlockBase {}
 * @endcode
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AllowInNavigation implements PluginPropertyInterface {

  /**
   * {@inheritdoc}
   */
  public function addToDefinition(string $id, array|object $definition): array {
    if (!is_array($definition)) {
      throw new InvalidPluginDefinitionException($id, sprintf('The %s attribute can only be used on plugins with an array definition, but the definition of plugin %s is an object.', static::class, $id));
    }
    $definition['allow_in_navigation'] = TRUE;
    return $definition;
  }

}
