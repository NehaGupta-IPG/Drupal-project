<?php

declare(strict_types=1);

namespace Drupal\Component\HtmlAttribute;

use Drupal\Component\Utility\Html;

/**
 * A class that defines a type of boolean HTML attribute.
 *
 * Boolean HTML attributes are not attributes with values of TRUE/FALSE.
 * They are attributes that if they exist in the tag, they are TRUE.
 * Examples include selected, disabled, checked, readonly.
 *
 * To set a boolean attribute on the HtmlAttributeCollection class, set it to
 * TRUE.
 * @code
 *  $attributes = new HtmlAttributeCollection();
 *  $attributes['disabled'] = TRUE;
 *  echo '<select' . $attributes . '/>';
 *  // produces <select disabled>;
 *  $attributes['disabled'] = FALSE;
 *  echo '<select' . $attributes . '/>';
 *  // produces <select>;
 * @endcode
 *
 * @see \Drupal\Component\HtmlAttribute\HtmlAttributeCollection
 */
class HtmlAttributeBoolean extends HtmlAttributeValueBase {

  /**
   * Constructs an HtmlAttributeBoolean object.
   *
   * @param string $name
   *   The name of the value.
   * @param bool $boolValue
   *   The value itself.
   */
  public function __construct(
    string $name,
    private bool $boolValue,
  ) {
    parent::__construct($name);
  }

  /**
   * Returns the raw value.
   *
   * @return bool
   *   The raw value.
   */
  protected function doGetValue(): bool {
    return $this->boolValue;
  }

  /**
   * {@inheritdoc}
   */
  public function render(): string {
    return $this->__toString();
  }

  /**
   * Implements the magic __toString() method.
   */
  public function __toString(): string {
    return $this->boolValue === FALSE ? '' : Html::escape($this->name);
  }

}
