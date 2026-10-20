<?php

namespace Drupal\Component\Utility;

/**
 * Processes email address domain input.
 */
final class EmailAddress {

  /**
   * Maps Unicode domain separators without changing local parts or names.
   *
   * Supports single mailboxes and mail API address lists. Quoted strings and
   * address literals are preserved. This does not validate the input.
   *
   * @see https://www.unicode.org/reports/tr46/#Mapping_Step_1
   */
  public static function normalizeDomainSeparators(string $value): string {
    $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
    if ($characters === FALSE) {
      return $value;
    }
    $normalized = '';
    $quoted = FALSE;
    $escaped = FALSE;
    $domain = FALSE;
    foreach ($characters as $character) {
      if ($escaped) {
        $escaped = FALSE;
      }
      elseif ($quoted && $character === '\\') {
        $escaped = TRUE;
      }
      elseif ($character === '"') {
        $quoted = !$quoted;
      }
      elseif (!$quoted) {
        if ($character === '@') {
          $domain = TRUE;
        }
        elseif (str_contains(" ,<>[]\t\r\n", $character)) {
          $domain = FALSE;
        }
        elseif ($domain) {
          $character = match ($character) {
            '。', '．', '｡' => '.',
            default => $character,
          };
        }
      }
      $normalized .= $character;
    }
    return $normalized;
  }

}
