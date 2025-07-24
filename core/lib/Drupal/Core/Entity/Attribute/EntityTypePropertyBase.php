<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Attribute;

use Drupal\Component\Plugin\Attribute\PluginPropertyInterface;

/**
 * Base class for attributes providing entity type definition property values.
 */
abstract class EntityTypePropertyBase implements PluginPropertyInterface {

  /**
   * The class name of the plugin associated with the attribute.
   *
   * @var string
   */
  protected string $pluginClass = '';

  /**
   * {@inheritdoc}
   */
  public function getPluginClass(): string {
    return $this->pluginClass;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginClass(string $class): static {
    $this->pluginClass = $class;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isValidPluginAttribute(string $pluginAttributeClass): bool {
    return is_a($pluginAttributeClass, EntityType::class, TRUE);
  }

}
