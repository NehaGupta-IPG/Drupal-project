<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Drupal\Core\Mailer\Attribute\EmailBody;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Default email plugin.
 */
class DefaultEmail extends EmailBase {

  /**
   * Create params array for default email plugin.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $subject
   *   Email subject.
   * @param \Stringable|\Stringable[]|null $lines
   *   Optional line or lines of text to use as the email body.
   * @param string|null $format
   *   Optional text format to use for the email body.
   */
  public static function createParams(TranslatableMarkup $subject, \Stringable|array|null $lines = NULL, ?string $format = NULL): array {
    return [
      'subject' => $subject,
      'lines' => match (TRUE) {
        is_array($lines) => $lines,
        !empty($lines) => [$lines],
        default => [],
      },
      'format' => $format,
    ];
  }

  /**
   * Builds an email body from a list of text lines.
   *
   * @param \Stringable[] $lines
   *   List of lines of text to use as the email body.
   * @param string|null $format
   *   Optional text format to use for the email body.
   * @param string|null $langcode
   *   Optional language code to use when rendering the email body.
   *
   * @return array
   *   The email body render array.
   */
  #[EmailBody]
  protected function getBody(?array $lines = [], ?string $format = NULL, ?string $langcode = NULL): array {
    $build = [];
    foreach ($lines as $line) {
      $build['lines'][] = [
        '#type' => 'processed_text',
        '#text' => (string) $line,
        '#format' => $format,
        '#langcode' => $langcode,
      ];
    }
    return $build;
  }

}
