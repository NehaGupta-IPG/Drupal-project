<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;

/**
 * Overriding the views filter plugin "dblog_types".
 */
class DblogTypes extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = _dblog_get_message_types();
    }
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    $form['value']['#access'] = !empty($form['value']['#options']);
  }

}
