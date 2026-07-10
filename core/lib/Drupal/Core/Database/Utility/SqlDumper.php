<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Utility;

use Doctrine\SqlFormatter\SqlFormatter;
use Doctrine\SqlFormatter\CliHighlighter;
use Doctrine\SqlFormatter\HtmlHighlighter;
use Doctrine\SqlFormatter\NullHighlighter;
use Drupal\Core\Render\Markup;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

/**
 * An utility class to help dumping an SQL statement and placeholders.
 */
abstract class SqlDumper {

  public static function export(string $sqlString, array $placeholders, ?bool $color = NULL, ?bool $html = NULL): string {

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
      return $sqlString . "; " . print_r($placeholders, TRUE);
    }

    // Format the SQL statement string.
    $highlighter = $html ? new HtmlHighlighter() : ($color ? new CliHighlighter() : new NullHighlighter());
    $formattedSql = (new SqlFormatter($highlighter))->format($sqlString);

    // Format the placeholders.
    $cloner = new VarCloner();
    $dumper = $html ? new HtmlDumper() : new CliDumper();
    $dumper->setColors($color);
    $dump = [];
    $dumper->dump(
      $cloner->cloneVar($placeholders),
      function (string $line, int $depth, string $indent_pad) use (&$dump): void {
        // A negative depth means "end of dump".
        if ($depth >= 0) {
          // Adds a two spaces indentation to the line.
          $dump[] = str_repeat($indent_pad, $depth) . $line;
        }
      }
    );
    $formattedArgs = $html ? implode("", $dump) : implode("\n", $dump);

    return static::output(
      $formattedSql,
      $formattedArgs,
      $html ? "<hr>" : "--------------------------------------------------------------------",
      $html ? "" : "\n",
      !$html && $color,
    );
  }

  private static function output(string $sqlDump, string $placeholderDump, string $delimiter, string $newLine, bool $resetColor): string {
    $output = static::stringOutput($delimiter . $newLine, $resetColor);
    $output .= static::stringOutput("SQL statement" . $newLine, $resetColor);
    $output .= static::stringOutput($delimiter . $newLine, $resetColor);
    $output .= static::stringOutput($sqlDump . $newLine, $resetColor);
    $output .= static::stringOutput($delimiter . $newLine, $resetColor);
    $output .= static::stringOutput("Placeholders" . $newLine, $resetColor);
    $output .= static::stringOutput($delimiter . $newLine, $resetColor);
    $output .= static::stringOutput($placeholderDump . $newLine, $resetColor);
    $output .= static::stringOutput($delimiter . $newLine, $resetColor);

    return $output;
  }

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
