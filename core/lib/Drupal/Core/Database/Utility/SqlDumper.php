<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Utility;

use Doctrine\SqlFormatter\SqlFormatter;
use Doctrine\SqlFormatter\CliHighlighter;
use Doctrine\SqlFormatter\HtmlHighlighter;
use Doctrine\SqlFormatter\NullHighlighter;
use Drupal\Core\Database\SqlExecutionInfo;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

/**
 * An utility class for dumping an SQL statement and its placeholders.
 */
class SqlDumper {

  /**
   * Exports the SQL statement information to a string.
   *
   * This is used to make database exceptions more readable both on site pages
   * and in CLI (tests or dr). The method detects whether we are in a CLI or
   * a HTML environment and dumps information accordingly.
   *
   * @param \Drupal\Core\Database\SqlExecutionInfo|null $info
   *   The SQL statement execution information, or NULL if none is available.
   * @param ?bool $color
   *   (optional) whether output should be colored. If not specified, it will
   *   be TRUE for CLI output, irrelevant for HTML output.
   * @param ?bool $html
   *   (optional) whether output should be in HTML format. If not specified, it
   *   will be FALSE for CLI output, TRUE for HTML output.
   *
   * @return string
   *   The output for printing, or an empty string if there is no information
   *   to export.
   */
  public function export(
    ?SqlExecutionInfo $info,
    ?bool $color = NULL,
    ?bool $html = NULL,
  ): string {

    // This runs while an exception is being reported, so it must never fail.
    if ($info === NULL) {
      return '';
    }

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
      return ": " . $info->sqlString . "; " . print_r($info->placeholders, TRUE);
    }

    // Format the SQL statement string.
    $highlighter = $html ? new HtmlHighlighter() : ($color ? new CliHighlighter() : new NullHighlighter());
    $formattedSql = (new SqlFormatter($highlighter))->format($info->sqlString);

    $cloner = new VarCloner();
    $dumper = $html ? new HtmlDumper() : new CliDumper();
    if ($html) {
      $dumper->setTheme('light');
    }
    else {
      $dumper->setColors($color);
    }

    // Format the placeholders.
    $formattedArgs = !empty($info->placeholders) ? $dumper->dump($cloner->cloneVar($info->placeholders), TRUE) : NULL;

    // Format the options.
    $formattedOptions = !empty($info->options) ? $dumper->dump($cloner->cloneVar($info->options), TRUE) : NULL;

    return $this->output(
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
  protected function output(
    ?string $sqlDump,
    ?string $placeholderDump,
    ?string $optionDump,
    string $delimiter,
    string $newLine,
    bool $resetColor,
  ): string {
    $output = $this->stringOutput($delimiter . $newLine, $resetColor);
    if ($sqlDump !== NULL) {
      $output .= $this->stringOutput("SQL statement" . $newLine, $resetColor);
      $output .= $this->stringOutput($delimiter . $newLine, $resetColor);
      $output .= $this->stringOutput($sqlDump . $newLine, $resetColor);
      $output .= $this->stringOutput($delimiter . $newLine, $resetColor);
    }
    if ($placeholderDump !== NULL) {
      $output .= $this->stringOutput("Placeholders" . $newLine, $resetColor);
      $output .= $this->stringOutput($delimiter . $newLine, $resetColor);
      $output .= $this->stringOutput($placeholderDump . $newLine, $resetColor);
      $output .= $this->stringOutput($delimiter . $newLine, $resetColor);
    }
    if ($optionDump !== NULL) {
      $output .= $this->stringOutput("Options" . $newLine, $resetColor);
      $output .= $this->stringOutput($delimiter . $newLine, $resetColor);
      $output .= $this->stringOutput($optionDump . $newLine, $resetColor);
      $output .= $this->stringOutput($delimiter . $newLine, $resetColor);
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
  private function stringOutput(string $string, bool $resetColor): string {
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
