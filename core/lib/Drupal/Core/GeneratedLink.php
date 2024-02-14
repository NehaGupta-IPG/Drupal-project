<?php

namespace Drupal\Core;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Used to return generated links and their related cacheability data.
 *
 * Note: Don't confuse this with \Drupal\Core\Link. That's used for dealing
 *  with links that are not yet generated (typically, these can be a combination of
 *  link text, route name, and route parameters).
 */
class GeneratedLink extends BubbleableMetadata implements MarkupInterface, \Countable {

  /**
   * HTML tag to use when building the link.
   */
  const TAG = 'a';

  /**
   * The HTML string value containing a link.
   *
   * @var string
   */
  protected $generatedLink = '';

  /**
   * Gets the generated link.
   *
   * @return string
   *   The generated link.
   */
  public function getGeneratedLink() {
    return $this->generatedLink;
  }

  /**
   * Sets the generated link.
   *
   * @param string $generated_link
   *   The generated link.
   *
   * @return $this
   */
  public function setGeneratedLink($generated_link) {
    $this->generatedLink = $generated_link;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return (string) $this->generatedLink;
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
