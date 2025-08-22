<?php

namespace Drupal\Core;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Template\Attribute;

/**
 * Used to return generated links, along with associated cacheability metadata.
 *
 * Note: not to be confused with \Drupal\Core\Link, which is for passing around
 *   links that are not generated (typically link text + route name + route
 *   parameters).
 */
class GeneratedLink extends BubbleableMetadata implements MarkupInterface, \Countable {

  /**
   * HTML tag to use when building the link.
   */
  const TAG = 'a';

  /**
   * The HTML string value containing a link.
   *
   * This property is generated automatically now, so its needed to use
   * ::getGeneratedLink().
   *
   * @var string
   */
  protected $generatedLink;

  /**
   * The link attributes.
   *
   * @var array
   */
  protected $attributes = [];

  /**
   * The link text.
   *
   * @var string|\Drupal\Component\Render\MarkupInterface
   */
  protected $text;

  /**
   * Creates a new GeneratedLink instance.
   *
   * @internal Use \Drupal\Core\GeneratedLink::create instance.
   */
  public function __construct() {
  }

  /**
   * Creates a new GeneratedLink instance.
   *
   * @param array $attributes
   *   The link attributes.
   * @param string|\Drupal\Component\Render\MarkupInterface $text
   *   The link text.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface|mixed $object
   *   Adds the object's bubble metadata to the generated link.
   *
   * @return static
   */
  public static function create(array $attributes, $text, $object = NULL) {
    if ($object) {
      $generated_link = static::createFromObject($object);
    }
    else {
      $generated_link = new static();
    }
    $generated_link->attributes = $attributes;
    $generated_link->text = $text;
    return $generated_link;
  }

  /**
   * Gets the generated link.
   *
   * @return string
   *   The generated link.
   */
  public function getGeneratedLink() {
    if (!isset($this->generatedLink)) {
      $attributes = new Attribute($this->attributes);
      $text = $this->text;
      if (!($text instanceof MarkupInterface)) {
        $text = Html::escape($text);
      }
      // This is safe because Attribute does escaping and $variables['text'] is
      // either rendered or escaped.
      $this->generatedLink = '<' . static::TAG . $attributes . '>' . $text . '</' . static::TAG . '>';
    }
    return $this->generatedLink;
  }

  /**
   * Sets the generated link.
   *
   * @param string $generated_link
   *   The generated link.
   *
   * @return static
   *  A new generated link.
   */
  public function setGeneratedLink($generated_link) {
    // Copy over all bubbleable metadata.
    $new_generated_link = static::createFromObject($this);
    $new_generated_link->generatedLink = $generated_link;
    return $new_generated_link;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return (string) $this->getGeneratedLink();
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): string {
    return $this->__toString();
  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    return mb_strlen($this->__toString());
  }

}
