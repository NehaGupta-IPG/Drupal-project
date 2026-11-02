<?php

namespace Drupal\Component\Utility;

/**
 * Provides Unicode-related conversions and operations.
 *
 * @ingroup utility
 */
class Unicode {

  /**
   * Matches Unicode characters that are word boundaries.
   *
   * Characters with the following General_category (gc) property values are
   * used as word boundaries.
   * - Cc, Cf, Cn, Co, Cs: Other.
   * - Pc, Pd, Pe, Pf, Pi, Po, Ps: Punctuation.
   * - Sc, Sk, Sm, So: Symbols.
   * - Zl, Zp, Zs: Separators.
   *
   * Non-boundary characters include the following General_category (gc)
   * property values:
   * - Ll, Lm, Lo, Lt, Lu: Letters.
   * - Mc, Me, Mn: Combining Marks.
   * - Nd, Nl, No: Numbers.
   *
   * @see http://unicode.org/glossary
   */
  const PREG_CLASS_WORD_BOUNDARY = <<<'EOD'
\p{Cc}\p{Cf}\p{Cn}\p{Co}\p{Cs}\p{Pc}\p{Pd}\p{Pe}\p{Pf}\p{Pi}\p{Po}\p{Ps}\p{Sc}\p{Sk}\p{Sm}\p{So}\p{Zl}\p{Zp}\p{Zs}
EOD;

  /**
   * Indicates that standard PHP (emulated) unicode support is being used.
   */
  const STATUS_SINGLEBYTE = 0;

  /**
   * Indicates that full unicode support with PHP mbstring extension is used.
   */
  const STATUS_MULTIBYTE = 1;

  /**
   * Indicates an error during check for PHP unicode support.
   */
  const STATUS_ERROR = -1;

  /**
   * Gets the current status of unicode/multibyte support on this environment.
   *
   * @return int
   *   The status of multibyte support. It can be one of:
   *   - \Drupal\Component\Utility\Unicode::STATUS_MULTIBYTE
   *     Full unicode support using an extension.
   *   - \Drupal\Component\Utility\Unicode::STATUS_SINGLEBYTE
   *     Standard PHP (emulated) unicode support.
   *   - \Drupal\Component\Utility\Unicode::STATUS_ERROR
   *     An error occurred. No unicode support.
   */
  public static function getStatus() {
    switch (static::check()) {
      case 'mb_strlen':
        return Unicode::STATUS_SINGLEBYTE;

      case '':
        return Unicode::STATUS_MULTIBYTE;
    }
    return Unicode::STATUS_ERROR;
  }

  /**
   * Checks for Unicode support in PHP and sets the proper settings if possible.
   *
   * Because of the need to be able to handle text in various encodings, we do
   * not support mbstring function overloading. HTTP input/output conversion
   * must be disabled for similar reasons.
   *
   * @return string
   *   A string identifier of a failed multibyte extension check, if any.
   *   Otherwise, an empty string.
   */
  public static function check() {
    // Set appropriate configuration.
    mb_internal_encoding('utf-8');
    mb_language('uni');

    // Check for mbstring extension.
    if (!extension_loaded('mbstring')) {
      return 'mb_strlen';
    }

    // Check mbstring configuration.
    if (ini_get('mbstring.encoding_translation') != 0) {
      return 'mbstring.encoding_translation';
    }

    return '';
  }

  /**
   * Decodes UTF byte-order mark (BOM) to the encoding name.
   *
   * @param string $data
   *   The data possibly containing a BOM. This can be the entire contents of
   *   a file, or just a fragment containing at least the first five bytes.
   *
   * @return string|false
   *   The name of the encoding, or FALSE if no byte order mark was present.
   */
  public static function encodingFromBOM($data) {
    static $bomMap = [
      "\xEF\xBB\xBF" => 'UTF-8',
      "\xFE\xFF" => 'UTF-16BE',
      "\xFF\xFE" => 'UTF-16LE',
      "\x00\x00\xFE\xFF" => 'UTF-32BE',
      "\xFF\xFE\x00\x00" => 'UTF-32LE',
      "\x2B\x2F\x76\x38" => 'UTF-7',
      "\x2B\x2F\x76\x39" => 'UTF-7',
      "\x2B\x2F\x76\x2B" => 'UTF-7',
      "\x2B\x2F\x76\x2F" => 'UTF-7',
      "\x2B\x2F\x76\x38\x2D" => 'UTF-7',
    ];

    foreach ($bomMap as $bom => $encoding) {
      if (str_starts_with($data, $bom)) {
        return $encoding;
      }
    }
    return FALSE;
  }

  /**
   * Converts data to UTF-8.
   *
   * Requires the iconv, GNU recode or mbstring PHP extension.
   *
   * @param string $data
   *   The data to be converted.
   * @param string $encoding
   *   The encoding that the data is in.
   *
   * @return string|false
   *   Converted data or FALSE.
   */
  public static function convertToUtf8($data, $encoding) {
    return @iconv($encoding, 'utf-8', $data);
  }

  /**
   * Truncates a UTF-8-encoded string safely to a number of bytes.
   *
   * If the end position is in the middle of a UTF-8 sequence, it scans
   * backwards until the beginning of the byte sequence.
   *
   * Use this function whenever you want to chop off a string at an unsure
   * location. On the other hand, if you're sure that you're splitting on a
   * character boundary (e.g. after using strpos() or similar), you can safely
   * use substr() instead.
   *
   * @param string $string
   *   The string to truncate.
   * @param int $len
   *   An upper limit on the returned string length.
   *
   * @return string
   *   The truncated string.
   */
  public static function truncateBytes($string, $len) {
    if (strlen($string) <= $len) {
      return $string;
    }
    if ((ord($string[$len]) < 0x80) || (ord($string[$len]) >= 0xC0)) {
      return substr($string, 0, $len);
    }
    // Scan backwards to beginning of the byte sequence.
    // @todo Make the code more readable in https://www.drupal.org/node/2911497.
    while (--$len >= 0 && ord($string[$len]) >= 0x80 && ord($string[$len]) < 0xC0) {
    }

    return substr($string, 0, $len);
  }

  /**
   * Capitalizes the first character of a UTF-8 string.
   *
   * @param string $text
   *   The string to convert.
   *
   * @return string
   *   The string with the first character as uppercase.
   */
  public static function ucfirst($text) {
    return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
  }

  /**
   * Converts the first character of a UTF-8 string to lowercase.
   *
   * @param string $text
   *   The string that will be converted.
   *
   * @return string
   *   The string with the first character as lowercase.
   *
   * @ingroup php_wrappers
   */
  public static function lcfirst($text) {
    // Note: no mbstring equivalent!
    return mb_strtolower(mb_substr($text, 0, 1)) . mb_substr($text, 1);
  }

  /**
   * Capitalizes the first character of each word in a UTF-8 string.
   *
   * @param string $text
   *   The text that will be converted.
   *
   * @return string
   *   The input $text with each word capitalized.
   *
   * @ingroup php_wrappers
   */
  public static function ucwords($text) {
    $regex = '/(^|[' . static::PREG_CLASS_WORD_BOUNDARY . '])([^' . static::PREG_CLASS_WORD_BOUNDARY . '])/u';
    return preg_replace_callback($regex, function (array $matches) {
      return $matches[1] . mb_strtoupper($matches[2]);
    }, $text);
  }

  /**
   * Truncates a UTF-8-encoded string safely to a number of characters.
   *
   * @param string $string
   *   The string to truncate.
   * @param int $max_length
   *   An upper limit on the returned string length, including trailing ellipsis
   *   if $add_ellipsis is TRUE.
   * @param bool $wordsafe
   *   (optional) If TRUE, attempt to truncate on a word boundary. Word
   *   boundaries are spaces, punctuation, and Unicode characters used as word
   *   boundaries in non-Latin languages; see Unicode::PREG_CLASS_WORD_BOUNDARY
   *   for more information. If a word boundary cannot be found that would make
   *   the length of the returned string fall within length guidelines (see
   *   parameters $max_length and $min_wordsafe_length), word boundaries are
   *   ignored. Defaults to FALSE, which means the string will be truncated
   *   at the exact character position without considering word boundaries.
   * @param bool $add_ellipsis
   *   (optional) If TRUE, add '...' to the end of the truncated string. The
   *   string length will still fall within $max_length. Defaults to FALSE,
   *   which means no ellipsis will be added to the truncated string.
   * @param int $min_wordsafe_length
   *   (optional) If $wordsafe is TRUE, the minimum acceptable length for
   *   truncation (before adding an ellipsis, if $add_ellipsis is TRUE). Has no
   *   effect if $wordsafe is FALSE. This can be used to prevent having a very
   *   short resulting string that will not be understandable. For instance, if
   *   you are truncating the string "See MyVeryLongURLExample.com for more
   *   information" to a word-safe return length of 20, the only available word
   *   boundary within 20 characters is after the word "See", which wouldn't
   *   leave a very informative string. If you had set $min_wordsafe_length to
   *   10, though, the function would realize that "See" alone is too short, and
   *   would then just truncate ignoring word boundaries, giving you
   *   "See MyVeryLongURL..." (assuming you had set $add_ellipsis to TRUE).
   *   Defaults to 1, which means any word-safe truncation result of at least
   *   1 character will be acceptable.
   *
   * @return string
   *   The truncated string.
   */
  public static function truncate($string, $max_length, $wordsafe = FALSE, $add_ellipsis = FALSE, $min_wordsafe_length = 1) {
    $ellipsis = '';
    $max_length = max($max_length, 0);
    $min_wordsafe_length = max($min_wordsafe_length, 0);

    if (mb_strlen($string) <= $max_length) {
      // No truncation needed, so don't add ellipsis, just return.
      return $string;
    }

    if ($add_ellipsis) {
      // Truncate ellipsis in case $max_length is small.
      $ellipsis = mb_substr('…', 0, $max_length);
      $max_length -= mb_strlen($ellipsis);
      $max_length = max($max_length, 0);
    }

    if ($max_length <= $min_wordsafe_length) {
      // Do not attempt word-safe if lengths are bad.
      $wordsafe = FALSE;
    }

    if ($wordsafe) {
      $matches = [];
      // Find the last word boundary, if there is one within
      // $min_wordsafe_length to $max_length characters. preg_match() is always
      // greedy, so it will find the longest string possible.
      $found = preg_match('/^(.{' . $min_wordsafe_length . ',' . $max_length . '})[' . Unicode::PREG_CLASS_WORD_BOUNDARY . ']/us', $string, $matches);
      if ($found) {
        $string = $matches[1];
      }
      else {
        $string = mb_substr($string, 0, $max_length);
      }
    }
    else {
      $string = mb_substr($string, 0, $max_length);
    }

    if ($add_ellipsis) {
      // If we're adding an ellipsis, remove any trailing periods.
      $string = rtrim($string, '.');

      $string .= $ellipsis;
    }

    return $string;
  }

  /**
   * Compares UTF-8-encoded strings in a binary safe case-insensitive manner.
   *
   * @param string $str1
   *   The first string.
   * @param string $str2
   *   The second string.
   *
   * @return int
   *   Returns < 0 if $str1 is less than $str2; > 0 if $str1 is greater than
   *   $str2, and 0 if they are equal.
   */
  public static function strcasecmp($str1, $str2) {
    return strcmp(mb_strtoupper($str1), mb_strtoupper($str2));
  }

  /**
   * Checks whether a string is valid UTF-8.
   *
   * All functions designed to filter input should use drupal_validate_utf8
   * to ensure they operate on valid UTF-8 strings to prevent bypass of the
   * filter.
   *
   * When text containing an invalid UTF-8 lead byte (0xC0 - 0xFF) is presented
   * as UTF-8 to Internet Explorer 6, the program may misinterpret subsequent
   * bytes. When these subsequent bytes are HTML control characters such as
   * quotes or angle brackets, parts of the text that were deemed safe by
   * filters end up in locations that are potentially unsafe; An onerror
   * attribute that is outside of a tag, and thus deemed safe by a filter, can
   * be interpreted by the browser as if it were inside the tag.
   *
   * The function does not return FALSE for strings containing character codes
   * above U+10FFFF, even though these are prohibited by RFC 3629.
   *
   * @param string $text
   *   The text to check.
   *
   * @return bool
   *   TRUE if the text is valid UTF-8, FALSE if not.
   */
  public static function validateUtf8($text) {
    if (strlen($text) == 0) {
      return TRUE;
    }
    // With the PCRE_UTF8 modifier 'u', preg_match() fails silently on strings
    // containing invalid UTF-8 byte sequences. It does not reject character
    // codes above U+10FFFF (represented by 4 or more octets), though.
    return (preg_match('/^./us', $text) == 1);
  }

}
