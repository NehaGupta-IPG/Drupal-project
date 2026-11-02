<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Command;

use Drupal\Component\Diff\DiffFormatter;
use Symfony\Component\Console\Formatter\OutputFormatter;

// cspell:ignore xbeg xlen ybeg ylen
// The method names are inherited from the diff engine.
// phpcs:disable Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps

/**
 * Diff formatter which returns a unified diff for the console.
 *
 * The lines are marked up with Symfony console tags, which are stripped again
 * when the output is not decorated.
 *
 * @internal
 */
final class ConsoleDiffFormatter extends DiffFormatter {

  /**
   * The formatted lines of the diff.
   *
   * @var string[]
   */
  private array $lines = [];

  public function __construct() {
    // Show the same amount of context as `diff -u` does.
    $this->leading_context_lines = 3;
    $this->trailing_context_lines = 3;
  }

  /**
   * {@inheritdoc}
   */
  protected function _start_diff() {
    $this->lines = [];
  }

  /**
   * {@inheritdoc}
   */
  protected function _end_diff() {
    return $this->lines === [] ? '' : implode(PHP_EOL, $this->lines) . PHP_EOL;
  }

  /**
   * {@inheritdoc}
   */
  protected function _block_header($xbeg, $xlen, $ybeg, $ylen) {
    // A unified diff numbers an empty range from zero.
    return \sprintf(
      '<fg=cyan>@@ -%d,%d +%d,%d @@</>',
      $xlen === 0 ? 0 : $xbeg,
      $xlen,
      $ylen === 0 ? 0 : $ybeg,
      $ylen,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function _start_block($header) {
    if ($this->show_header) {
      $this->lines[] = $header;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function _lines($lines, $prefix = ' ', $color = NULL) {
    foreach ($lines as $line) {
      $line = $prefix . OutputFormatter::escape((string) $line);
      $this->lines[] = $color === NULL ? $line : \sprintf('<fg=%s>%s</>', $color, $line);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function _added($lines) {
    $this->_lines($lines, '+', 'green');
  }

  /**
   * {@inheritdoc}
   */
  protected function _deleted($lines) {
    $this->_lines($lines, '-', 'red');
  }

  /**
   * {@inheritdoc}
   */
  protected function _changed($orig, $closing) {
    $this->_deleted($orig);
    $this->_added($closing);
  }

}
