<?php

declare(strict_types=1);

namespace Drupal\system\Hook;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Utility\Token;

/**
 * Mail hook implementations for system.
 */
class SystemMailHooks {

  public function __construct(
    protected readonly Token $token,
  ) {}

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail($key, &$message, $params): void {
    $context = $params['context'];
    $subject = PlainTextOutput::renderFromHtml($this->token->replace($context['subject'], $context));
    $body = $this->token->replace($context['message'], $context);
    $message['subject'] .= str_replace(["\r", "\n"], '', $subject);
    $message['body'][] = $body;
  }

}
