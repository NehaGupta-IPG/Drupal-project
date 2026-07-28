<?php

declare(strict_types=1);

namespace Drupal\field_ui\Attribute;

use Drupal\Component\Plugin\Attribute\PluginPropertyInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Sets the route the Field UI tabs of an entity type are attached to.
 *
 * Place this attribute on an entity type class alongside the entity type
 * attribute. The 'field_ui_base_route' property is only set when the field_ui
 * module is installed.
 *
 * For example:
 * @code
 * use Drupal\Core\Entity\Attribute\ContentEntityType;
 * use Drupal\Core\Entity\ContentEntityBase;
 * use Drupal\Core\StringTranslation\TranslatableMarkup;
 * use Drupal\field_ui\Attribute\FieldUiBaseRoute;
 *
 * #[ContentEntityType(
 *   id: 'example',
 *   label: new TranslatableMarkup('Example'),
 * )]
 * #[FieldUiBaseRoute('entity.example_type.edit_form')]
 * class Example extends ContentEntityBase {}
 * @endcode
 *
 * @see \Drupal\field_ui\Routing\RouteSubscriber
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FieldUiBaseRoute implements PluginPropertyInterface {

  /**
   * Constructs a FieldUiBaseRoute attribute object.
   *
   * @param string $route
   *   The name of the route the Field UI tabs are attached to.
   */
  public function __construct(
    public readonly string $route,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addToDefinition(string $id, array|object $definition): EntityTypeInterface {
    if (!$definition instanceof EntityTypeInterface) {
      throw new InvalidPluginDefinitionException($id, sprintf('The %s attribute can only be used on entity types, but plugin %s is not an entity type.', static::class, $id));
    }
    return $definition->set('field_ui_base_route', $this->route);
  }

}
