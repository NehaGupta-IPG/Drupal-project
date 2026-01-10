<?php

namespace Drupal\block;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;

/**
 * Provides a collection of block plugins.
 */
class BlockPluginCollection extends DefaultSingleLazyPluginCollection {

  /**
   * The block ID this plugin collection belongs to.
   *
   * @var string
   */
  protected $blockId;

  public function __construct(PluginManagerInterface $manager, $instance_id, array $configuration, $block_id) {
    parent::__construct($manager, $instance_id, $configuration);

    $this->blockId = $block_id;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Block\BlockPluginInterface
   *   The block plugin instance.
   */
  public function &get($instance_id) {
    return parent::get($instance_id);
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   *   Returns nothing.
   */
  public function addInstanceId($id, $configuration = NULL) {
    if (!$id) {
      throw new PluginException("The block '{$this->blockId}' did not specify a plugin.");
    }
    parent::addInstanceId($id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  protected function initializePlugin($instance_id) {
    if (!$instance_id) {
      throw new PluginException("The block '{$this->blockId}' did not specify a plugin.");
    }

    try {
      parent::initializePlugin($instance_id);
    }
    catch (PluginException $e) {
      $module = $this->configuration['provider'];
      // Ignore blocks belonging to uninstalled modules, but re-throw valid
      // exceptions when the module is installed and the plugin is
      // misconfigured.
      if (!$module || \Drupal::moduleHandler()->moduleExists($module)) {
        throw $e;
      }
    }
  }

}
