<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\node\Plugin\views\field\Node as CoreNode;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Overriding the views field plugin "node".
 */
class Node extends CoreNode {

  use FieldPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    FieldPluginBase::init($view, $display, $options);

    // Don't add the additional fields to groupby.
    if (!empty($this->options['link_to_node'])) {
      $this->additional_fields['nid'] = ['table' => 'node', 'field' => 'nid'];
    }
  }

}
