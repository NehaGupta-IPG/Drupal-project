<?php

namespace Drupal\views\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Provides an AJAX command for replacing the page title.
 *
 * This command is implemented in
 * Drupal.AjaxCommands.prototype.viewsReplaceTitle.
 */
class ReplaceTitleCommand implements CommandInterface {

  /**
   * The page title to replace.
   *
   * @var string
   */
  protected $title;

  public function __construct($title) {
    $this->title = $title;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'viewsReplaceTitle',
      'title' => $this->title,
      'siteName' => \Drupal::config('system.site')->get('name'),
    ];
  }

}
