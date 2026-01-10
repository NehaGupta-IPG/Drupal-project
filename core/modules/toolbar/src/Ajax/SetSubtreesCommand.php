<?php

namespace Drupal\toolbar\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Defines an AJAX command that sets the toolbar subtrees.
 */
class SetSubtreesCommand implements CommandInterface {

  /**
   * The toolbar subtrees.
   *
   * @var array
   */
  protected $subtrees;

  public function __construct($subtrees) {
    $this->subtrees = $subtrees;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'setToolbarSubtrees',
      'subtrees' => array_map('strval', $this->subtrees),
    ];
  }

}
