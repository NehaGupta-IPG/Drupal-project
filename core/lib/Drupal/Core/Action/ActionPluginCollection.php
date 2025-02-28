<?php

namespace Drupal\Core\Action;

use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;

/**
 * Provides a container for lazily loading Action plugins.
 */
class ActionPluginCollection extends DefaultSingleLazyPluginCollection {

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Action\ActionInterface
   *   The action plugin instance.
   */
  // phpcs:ignore Drupal.Commenting.FunctionComment.MissingReturnComment, Generic.CodeAnalysis.UselessOverridingMethod
  public function &get($instance_id) {
    return parent::get($instance_id);
  }

}
