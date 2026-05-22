<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\mongodb\modules\views\ManyToOneHelper;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\views\ViewExecutable;

/**
 * Overriding the views filter plugin "many_to_one".
 */
trait ManyToOneTrait {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    InOperator::init($view, $display, $options);

    $this->helper = new ManyToOneHelper($this);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['operator']['default'] = 'or';
    $options['value']['default'] = [];

    if (isset($this->helper)) {
      $this->helper->defineOptions($options);
    }
    else {
      $helper = new ManyToOneHelper($this);
      $helper->defineOptions($options);
    }

    return $options;
  }

}
