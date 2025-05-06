<?php

namespace Drupal\views\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\views\Plugin\Block\ViewsBlock;
use Drupal\views\Plugin\views\display\Block;

/**
 * Event fired during \Drupal\views\Plugin\views\display\Block::preBuildBlock().
 *
 * Subscribers to this event can prepare.
 *
 * @package Drupal\views\Event
 */
class PreBuildBlockEvent extends Event {

  /**
   * The views block instance.
   *
   * @var \Drupal\views\Plugin\Block\ViewsBlock
   */
  protected $block;

  /**
   * The views block display handler.
   *
   * @var \Drupal\views\Plugin\views\display\Block
   */
  protected $display;

  /**
   * Constructs a new PreBuildBlockEvent.
   *
   * @param \Drupal\views\Plugin\Block\ViewsBlock $block
   *   The views block instance.
   * @param Drupal\views\Plugin\views\display\Block $display
   *   The views block display handler.
   */
  public function __construct($block, $display) {
    $this->block = $block;
    $this->display = $display;
  }

  /**
   * Gets the view block.
   *
   * @return \Drupal\views\Plugin\Block\ViewsBlock
   *   The views block instance.
   */
  public function getBlock(): ViewsBlock {
    return $this->block;
  }

  /**
   * Gets the views display.
   *
   * @return \Drupal\views\Plugin\views\display\Block
   *   The views block display handler.
   */
  public function getDisplay(): Block {
    return $this->display;
  }

}
