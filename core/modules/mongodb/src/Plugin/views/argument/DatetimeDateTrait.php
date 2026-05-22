<?php

namespace Drupal\mongodb\Plugin\views\argument;

// cspell:ignore datestring

/**
 * Trait for overriding the Drupal\datetime\Plugin\views\argument\Date class.
 */
trait DatetimeDateTrait {

  use FormulaTrait;

  /**
   * The MongoDB condition operator.
   *
   * @var string
   */
  protected $mongodbOperator = 'DATESTRING';

  /**
   * {@inheritdoc}
   */
  public function getDateField() {
    return "$this->tableAlias.$this->realField";
  }

}
