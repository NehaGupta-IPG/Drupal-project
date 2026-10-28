<?php

namespace Drupal\Core\Template;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\HtmlAttribute\Attribute as ComponentAttribute;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Render\AttachmentsTrait;
use Drupal\Core\Serialization\Attribute\JsonSchema;
use Drupal\Core\Theme\Style\StyleDefinitionInterface;

/**
 * Collects, sanitizes, and renders HTML attributes.
 *
 * This class adds MarkupInterface, JSON serialization, merging, styles and
 * attachments to the parent class. See the parent class for usage.
 *
 * @see \Drupal\Component\Utility\HtmlAttribute\Attribute
 */
class Attribute extends ComponentAttribute implements MarkupInterface, AttachmentsInterface, CacheableDependencyInterface {

  use AttachmentsTrait;

  /**
   * Add style into an Attribute object. Handle library dependency.
   *
   * Allows Twig templates to set style using
   * @code
   * {{ attributes.addStyle(style('my_style'), 'my_value') }}
   * @endcode
   *
   * @param \Drupal\Core\Theme\Style\StyleDefinitionInterface|null $styleDefinition
   *   The style definition, as resolved by
   *   \Drupal\Core\Template\TwigExtension::getStyle(). NULL if the style ID
   *   did not resolve to a valid definition, in which case this method does
   *   nothing.
   * @param string $styleOption
   *   The style option.
   *
   * @return $this
   */
  public function addStyle(?StyleDefinitionInterface $styleDefinition, string $styleOption) {
    $styleDefinition?->applyOrBubbleOnAttribute($styleOption, $this);

    return $this;
  }

  /**
   * Implements the magic __toString() method.
   *
   * This method does not process attachments. Printing an Attribute object
   * from within a Twig template bubbles its attachments to the current
   * render context through
   * \Drupal\Core\Template\TwigExtension::bubbleArgMetadata(), since Attribute
   * implements \Drupal\Core\Render\AttachmentsInterface. Casting an
   * Attribute object to a string outside of Twig rendering (for example
   * directly in PHP) is safe, but discards any attachments, since there is no
   * render array for them to bubble into.
   *
   * @see \Drupal\Core\Template\TwigExtension::bubbleArgMetadata()
   */
  #[JsonSchema(['type' => 'string', 'description' => 'Rendered HTML element attributes'])]
  public function __toString() {
    return parent::__toString();
  }

  /**
   * Returns a representation of the object for use in JSON serialization.
   *
   * @return string
   *   The safe string content.
   */
  public function jsonSerialize(): string {
    return (string) $this;
  }

  /**
   * Merges an Attribute object into the current storage.
   *
   * @param \Drupal\Core\Template\Attribute $collection
   *   The Attribute object to merge.
   *
   * @return $this
   */
  public function merge(Attribute $collection) {
    $merged_attributes = NestedArray::mergeDeep($this->toArray(), $collection->toArray());
    foreach ($merged_attributes as $name => $value) {
      $this->storage[$name] = $this->createAttributeValue($name, $value);
    }

    // Merge attachments.
    $this->addAttachments($collection->getAttachments());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return Cache::PERMANENT;
  }

}
