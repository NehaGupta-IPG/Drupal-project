<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Utility;

use Doctrine\SqlFormatter\SqlFormatter;
use Doctrine\SqlFormatter\CliHighlighter;
use Doctrine\SqlFormatter\HtmlHighlighter;
use Doctrine\SqlFormatter\NullHighlighter;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

/**
 * An utility class for dumping an SQL statement and its placeholders.
 */
abstract class SqlDumper {

  /**
   * Exports the SQL statement information to a string.
   *
   * This is used to make database exceptions more readable both on site pages
   * and in CLI (tests or dr). The method detects whether we are in a CLI or
   * a HTML environment and dumps information accordingly.
   *
   * @param string $sqlString
   *   The SQL statement string.
   * @param list<array|int|float|string|bool|null>|array<string,array|int|float|string|bool|null> $placeholders
   *   The placeholder values, keyed by placeholder name for a string
   *   containing named placeholders, 0-indexed for a string containing
   *   positional placeholders.
   * @param array<string,mixed> $options
   *   An associative array of options to control how the database operation is
   *   run.
   * @param ?bool $color
   *   (optional) whether output should be colored. If not specified, it will
   *   be TRUE for CLI output, irrelevant for HTML output.
   * @param ?bool $html
   *   (optional) whether output should be in HTML format. If not specified, it
   *   will be FALSE for CLI output, TRUE for HTML output.
   *
   * @return string
   *   The output for printing.
   */
  public static function export(
    string $sqlString,
    array $placeholders,
    array $options,
    ?bool $color = NULL,
    ?bool $html = NULL,
  ): string {

    if (PHP_SAPI === 'cli') {
      $color ??= TRUE;
      $html ??= FALSE;
    }
    else {
      $color ??= FALSE;
      $html ??= TRUE;
    }

    // In prod environments, keep using a minimal output.
    if (!class_exists(SqlFormatter::class) || !class_exists(VarCloner::class)) {
      return ": " . $sqlString . "; " . print_r($placeholders, TRUE);
    }

    // Format the SQL statement string.
    $highlighter = $html ? new HtmlHighlighter() : ($color ? new CliHighlighter() : new NullHighlighter());
    $formattedSql = (new SqlFormatter($highlighter))->format($sqlString);

    $cloner = new VarCloner();
    $dumper = $html ? new HtmlDumper() : new CliDumper();
    if ($html) {
      $dumper->setTheme('light');
    }
    else {
      $dumper->setColors($color);
    }

    // Format the placeholders.
    $formattedArgs = !empty($placeholders) ? $dumper->dump($cloner->cloneVar($placeholders), TRUE) : NULL;

    // Format the options.
    $formattedOptions = !empty($options) ? $dumper->dump($cloner->cloneVar($options), TRUE) : NULL;

    return static::output(
      $formattedSql,
      $formattedArgs,
      $formattedOptions,
      $html ? "<hr>" : "------------------------------------------------------------",
      $html ? "" : "\n",
      !$html && $color,
    );
  }

  /**
   * Formats ::export() output.
   *
   * @param string $sqlDump
   *   The processed SQL statement dump string.
   * @param ?string $placeholderDump
   *   The processed placeholders values dump string. NULL for no placeholders.
   * @param ?string $optionDump
   *   The processed options values dump string. NULL for no options.
   * @param string $delimiter
   *   A string to be used for vertical breaks.
   * @param string $newLine
   *   A string to be added at the ned of each line.
   * @param bool $resetColor
   *   Whether an ANSI color reset directive should be prefixed for each line.
   *
   * @return string
   *   The output for printing.
   */
  protected static function output(
    string $sqlDump,
    ?string $placeholderDump,
    ?string $optionDump,
    string $delimiter,
    string $newLine,
    bool $resetColor,
  ): string {
    $output = static::stringOutput($delimiter . $newLine, $resetColor);
    $output .= static::stringOutput("SQL statement" . $newLine, $resetColor);
    $output .= static::stringOutput($delimiter . $newLine, $resetColor);
    $output .= static::stringOutput($sqlDump . $newLine, $resetColor);
    $output .= static::stringOutput($delimiter . $newLine, $resetColor);
    if ($placeholderDump !== NULL) {
      $output .= static::stringOutput("Placeholders" . $newLine, $resetColor);
      $output .= static::stringOutput($delimiter . $newLine, $resetColor);
      $output .= static::stringOutput($placeholderDump . $newLine, $resetColor);
      $output .= static::stringOutput($delimiter . $newLine, $resetColor);
    }
    if ($optionDump !== NULL) {
      $output .= static::stringOutput("Options" . $newLine, $resetColor);
      $output .= static::stringOutput($delimiter . $newLine, $resetColor);
      $output .= static::stringOutput($optionDump . $newLine, $resetColor);
      $output .= static::stringOutput($delimiter . $newLine, $resetColor);
    }

    return $output;
  }

  /**
   * Formats a single multi-line string for output.
   *
   * @param string $string
   *   The string to be output.
   * @param bool $resetColor
   *   Whether an ANSI color reset directive should be prefixed for each line.
   *
   * @return string
   *   The output for printing.
   */
  private static function stringOutput(string $string, bool $resetColor): string {
    if ($resetColor) {
      $tmp = explode("\n", $string);
      $tmp = array_map(function (string $value): string {
        return "\x1b[0m" . $value;
      }, $tmp);
      return implode("\n", $tmp);
    }
    return $string;
  }

}
