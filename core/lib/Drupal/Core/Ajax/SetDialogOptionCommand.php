<?php

namespace Drupal\Core\Ajax;

/**
 * Defines an AJAX command that sets jQuery UI dialog properties.
 *
 * @ingroup ajax
 */
class SetDialogOptionCommand implements CommandInterface {

  /**
   * A CSS selector string.
   *
   * @var string
   */
  protected $selector;

  /**
   * A jQuery UI dialog option name.
   *
   * @var string
   */
  protected $optionName;

  /**
   * A jQuery UI dialog option value.
   *
   * @var mixed
   */
  protected $optionValue;

  public function __construct($selector, $option_name, $option_value) {
    $this->selector = $selector ?: '#drupal-modal';
    $this->optionName = $option_name;
    $this->optionValue = $option_value;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'setDialogOption',
      'selector' => $this->selector,
      'optionName' => $this->optionName,
      'optionValue' => $this->optionValue,
    ];
  }

}
