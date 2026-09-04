<?php

declare(strict_types=1);

namespace Drupal\test_htmx\Controller;

use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;

/**
 * Test Controller to show message links.
 */
class HtmxMessageTestController {

  /**
   * Gets the test types.
   *
   * @return string[]
   *   The test types.
   */
  public static function getTypes() {
    return ['status', 'error', 'warning'];
  }

  /**
   * Prepares a message and returns a log entry.
   *
   * @param string $type
   *   The type of message to add.
   *
   * @return array
   *   Render array for the log entry.
   */
  public function addMessageType(string $type): array {
    // Add PHP rendered messages to the page.
    $messenger = \Drupal::messenger();
    match ($type) {
      'status' => $messenger->addStatus("PHP Status"),
      'error' => $messenger->addError("PHP Error"),
      'warning' => $messenger->addWarning("PHP Warning"),
      default => $messenger->addMessage("PHP Message of type $type", $type),
    };
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => 'message-log',
      ],
      '#value' => "Message of type $type requested.",
    ];
  }

  /**
   * Displays buttons to show messages via HTMX.
   *
   * @return array
   *   Render array for htmx powered buttons.
   */
  public function messageButtons(): array {
    $buttons = [];
    $buttons['#attached']['library'][] = 'test_htmx/assets';
    $buttons['content'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => "Message buttons",
    ];
    foreach (static::getTypes() as $type) {
      $buttonUrl = Url::fromRoute('test_htmx.message', ['type' => $type]);
      $buttons['content']["add-$type"] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => "Add $type",
        '#attributes' => [
          'type' => 'button',
        ],
      ];
      (new Htmx())->get($buttonUrl)
        ->select('div.message-log')
        ->target('main')
        ->swap('beforeend')
        ->onlyMainContent()
        ->applyTo($buttons['content']["add-$type"]);
    }
    return $buttons;
  }

}
