<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Utility;

use Doctrine\SqlFormatter\CliHighlighter;
use Doctrine\SqlFormatter\Highlighter;
use Doctrine\SqlFormatter\HtmlHighlighter;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Drupal\Component\Utility\Html;
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
   * and in CLI (tests or dr). The format is not detected here: every caller
   * renders the exception for a known destination, and the SAPI does not
   * decide it. A CLI hosted kernel still responds with a page, and 'cli-server'
   * and 'phpdbg' are not 'cli'.
   *
   * @param \Drupal\Core\Database\SqlExecutionInfo|null $info
   *   The SQL statement execution information, or NULL if none is available.
   * @param bool $color
   *   Whether the output should be colored with ANSI escape sequences. Only a
   *   stream reporting itself as decorated may get them: a redirected stream
   *   must not, and neither must the JUnit log that run-tests.sh parses.
   *   Irrelevant for HTML output.
   * @param bool $html
   *   Whether the output should be in HTML format, for a page, rather than
   *   plain text for a terminal.
   *
   * @return string
   *   The output for printing, or an empty string if there is no information
   *   to export.
   */
  public function export(
    ?SqlExecutionInfo $info,
    bool $color,
    bool $html,
  ): string {

    // This runs while an exception is being reported, so it must never fail.
    if ($info === NULL) {
      return '';
    }

    // In prod environments, keep using a minimal output.
    if (!$this->hasFormatter()) {
      $dump = ": " . $info->sqlString . "; " . print_r($info->placeholders, TRUE);
      // The formatted output below is escaped by the highlighter and by the
      // var dumper. This one is not, and it contains the placeholder values,
      // which are usually user input. Callers mark the returned dump as safe
      // markup, so it has to be escaped here.
      return $html ? Html::escape($dump) : $dump;
    }

    // Format the SQL statement string.
    $highlighter = $html ? new HtmlHighlighter() : ($color ? new CliHighlighter() : new NullHighlighter());
    $formattedSql = $this->formatSql($info->sqlString, $highlighter);

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
   * Renders an SQL statement, reformatting it when that stays faithful.
   *
   * @param string $sqlString
   *   The SQL statement string, as it was executed.
   * @param \Doctrine\SqlFormatter\Highlighter $highlighter
   *   The highlighter to render the statement with.
   *
   * @return string
   *   The statement, reformatted if the formatter reproduces it, and only
   *   highlighted where it stands otherwise.
   */
  protected function formatSql(string $sqlString, Highlighter $highlighter): string {
    $formatter = new SqlFormatter($highlighter);

    // The formatter reads a backslash inside a single quoted string as an
    // escape character. That is MySQL behaviour rather than SQL standard, and
    // a database taking the backslash literally needs a LIKE condition to end
    // in ESCAPE '\', which is what the SQLite driver emits. The formatter then
    // takes the closing quote for an escaped one, swallows the rest of the
    // statement into the string and reports parentheses as unclosed that the
    // statement does close. Reformatting is cosmetic, while a warning about a
    // statement that is in fact well formed is misleading, so a statement the
    // formatter cannot reproduce is left as it was executed. Its keywords are
    // still highlighted, and the string it read wrong is coloured as one.
    //
    // Only a statement containing a backslash can be affected, so the check is
    // made for those alone.
    //
    // @see \Drupal\sqlite\Driver\Database\sqlite\Connection::$sqlOperators
    // @see \Drupal\Core\Database\Query\Condition::$conditionOperatorMap
    if (str_contains($sqlString, '\\') && !$this->isFormattedFaithfully($sqlString)) {
      return $formatter->highlight($sqlString);
    }

    return $formatter->format($sqlString);
  }

  /**
   * Checks that reformatting a statement changes nothing but its whitespace.
   *
   * @param string $sqlString
   *   The SQL statement string, as it was executed.
   *
   * @return bool
   *   TRUE if the formatter reproduces the statement, FALSE otherwise.
   */
  private function isFormattedFaithfully(string $sqlString): bool {
    // The plain formatter is used, so that the comparison is not made against
    // the markup of a highlighter.
    $formatted = (new SqlFormatter(new NullHighlighter()))->format($sqlString);
    return preg_replace('/\s+/', '', $formatted) === preg_replace('/\s+/', '', $sqlString);
  }

  /**
   * Determines whether the libraries for a formatted dump are available.
   *
   * The SQL formatter is a development dependency, so production sites get
   * the minimal dump instead.
   *
   * @return bool
   *   TRUE if the dump can be formatted, FALSE otherwise.
   */
  protected function hasFormatter(): bool {
    return class_exists(SqlFormatter::class) && class_exists(VarCloner::class);
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
    string $sqlDump,
    ?string $placeholderDump,
    ?string $optionDump,
    string $delimiter,
    string $newLine,
    bool $resetColor,
  ): string {
    $output = $this->stringOutput($delimiter . $newLine, $resetColor);
    $output .= $this->stringOutput("SQL statement" . $newLine, $resetColor);
    $output .= $this->stringOutput($delimiter . $newLine, $resetColor);
    $output .= $this->stringOutput($sqlDump . $newLine, $resetColor);
    $output .= $this->stringOutput($delimiter . $newLine, $resetColor);
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
