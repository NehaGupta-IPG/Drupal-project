<?php

declare(strict_types=1);

namespace Drupal\TestTools\TestRunner;

use Drupal\Core\Database\Database;
use Drupal\TestTools\TestRunner\Configuration as Config;
use PHPUnit\Runner\Version;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Process\PhpExecutableFinder;

// cspell:ignore wwwrun

/**
 * CLI printer.
 *
 * @internal
 */
class CliPrinter {

  public function __construct(
    protected StyleInterface $io,
    protected Config $config,
  ) {
  }

  public function startTable(): void {
    $this->io->title("Drupal test run");

    $rows = [
      ['Drupal Version', \Drupal::VERSION],
      ['PHP Version', \PHP_VERSION],
      ['PHP Binary', new PhpExecutableFinder()->find()],
      ['PHPUnit Version', Version::id()],
      ['PHPUnit configuration', $this->config->get('phpunit-configuration')],
    ];
    if ($this->config->get('dburl')) {
      $sut_connection_info = Database::getConnectionInfo();
      $sut_tasks_class = $sut_connection_info['default']['namespace'] . "\\Install\\Tasks";
      $sut_installer = new $sut_tasks_class();
      $sut_connection = Database::getConnection();
      $rows[] = ["Database", (string) $sut_installer->name()];
      $rows[] = ["Database Version", $sut_connection->version()];
    }
    $rows[] = ["Working directory", getcwd()];
    $rows[] = ["Concurrency", $this->config->get('concurrency')];

    $rows[] = new TableSeparator();
    $rows[] = ['Test discovery'];
    if ($this->config->get('all') || $this->config->get('module') || $this->config->get('directory')) {
      if ($this->config->get('types')) {
        $rows[] = ["PHPUnit test suite(s)", implode(', ', $this->config->get('types'))];
      }
      if ($this->config->get('module')) {
        $rows[] = ["Drupal module", $this->config->get('module')];
      }
      if ($this->config->get('directory')) {
        $rows[] = ["Directory", $this->config->get('directory')];
      }
      if ($this->config->getTests()) {
        $rows[] = ["PHPUnit test group(s)", implode(', ', $this->config->getTests())];
      }
    }
    elseif ($this->config->get('class')) {
      $classesArg = $this->config->getTests();
      $rows[] = ["Test class(es)", array_shift($classesArg)];
      foreach ($classesArg as $arg) {
        $rows[] = [NULL, $arg];
      }
    }
    elseif ($this->config->get('file')) {
      $filesArg = $this->config->getTests();
      $rows[] = ["Test file(s)", array_shift($filesArg)];
      foreach ($filesArg as $arg) {
        $rows[] = [NULL, $arg];
      }
    }
    else {
      if ($this->config->get('types')) {
        $rows[] = ["PHPUnit test suite(s)", implode(', ', $this->config->get('types'))];
      }
      if ($this->config->getTests()) {
        $rows[] = ["PHPUnit test group(s)", implode(', ', $this->config->getTests())];
      }
    }

    $rows[] = new TableSeparator();
    $rows[] = ["Test run started", date('l, F j, Y - H:i:s', $_SERVER['REQUEST_TIME'])];

    $this->io->table([], $rows);
  }

  /**
   * Dumps the list of tests in order of execution after sorting.
   *
   * @param array $tests
   *   The array of test class info.
   */
  public function testSequenceTable(array $tests): void {
    $this->io->title("Test execution sequence");

    $rightAligned = new TableStyle();
    $rightAligned->setPadType(\STR_PAD_LEFT);

    $rows = [];
    foreach ($tests as $testInfo) {
      $rows[] = [
        $testInfo['worker_sequence'],
        in_array('#slow', $testInfo['groups']) ? '#slow' : NULL,
        \trim_with_ellipsis($testInfo['group'], 15, \STR_PAD_RIGHT),
        $testInfo['tests_count'],
        \trim_with_ellipsis($testInfo['name'], 60, \STR_PAD_LEFT),
      ];
    }

    $this->io->createTable()
      ->setHeaders(['Seq', 'Slow?', 'Group', 'Cnt', 'Class'])
      ->setColumnStyle(0, $rightAligned)
      ->setColumnStyle(3, $rightAligned)
      ->setRows($rows)
      ->render();
  }

  /**
   * Dumps the list of tests in order of execution for a bin.
   *
   * @param int $bin
   *   The bin.
   * @param array $allTests
   *   The list of all test classes discovered.
   * @param array $tests
   *   The list of test class to run for this bin.
   */
  public function testBinSequenceTable(int $bin, array $allTests, array $tests): void {
    $this->io->title("Test execution sequence");

    $rightAligned = new TableStyle();
    $rightAligned->setPadType(\STR_PAD_LEFT);
    $inactiveCellStyle = new TableCellStyle([
      'fg' => 'gray',
    ]);
    $inactiveRightAlignedCellStyle = new TableCellStyle([
      'fg' => 'gray',
      'align' => 'right',
    ]);
    $activeCellStyle = new TableCellStyle([
      'fg' => 'bright-white',
    ]);
    $activeRightAlignedCellStyle = new TableCellStyle([
      'fg' => 'bright-white',
      'align' => 'right',
    ]);

    $this->io->writeln(" Tests marked *** will be executed in this PARALLEL BIN #{$bin}.");

    $rows = [];
    foreach ($allTests as $testInfo) {
      $inBin = isset($tests[$testInfo['name']]);
      $style = $inBin ? $activeCellStyle : $inactiveCellStyle;
      $rightAlignedStyle = $inBin ? $activeRightAlignedCellStyle : $inactiveRightAlignedCellStyle;
      $rows[] = [
        new TableCell($inBin ? "***" : '', ['style' => $style]),
        new TableCell((string) $testInfo['sorted_sequence'], ['style' => $rightAlignedStyle]),
        new TableCell($inBin ? (string) $tests[$testInfo['name']]['worker_sequence'] : '', ['style' => $rightAlignedStyle]),
        new TableCell(in_array('#slow', $testInfo['groups']) ? '#slow' : '', ['style' => $style]),
        new TableCell(\trim_with_ellipsis($testInfo['group'], 15, \STR_PAD_RIGHT), ['style' => $style]),
        new TableCell((string) $testInfo['tests_count'], ['style' => $rightAlignedStyle]),
        new TableCell(\trim_with_ellipsis($testInfo['name'], 60, \STR_PAD_LEFT), ['style' => $style]),
      ];
    }

    $this->io->createTable()
      ->setHeaders(['Bin', "Sort\nSeq", "Bin\nSeq", 'Slow?', 'Group', 'Cnt', 'Class'])
      ->setColumnStyle(1, $rightAligned)
      ->setColumnStyle(2, $rightAligned)
      ->setColumnStyle(5, $rightAligned)
      ->setRows($rows)
      ->render();
  }

  public function summaryTable(array $end, float $total_time, array $end_discovery, ?float $overall_test_time): void {
    $this->io->title("Summary");
    $wall_seconds = $end['time'] / 1000;
    $discovery_seconds = $end_discovery['time'] / 1000;
    $formatter = \Drupal::service('date.formatter');

    $rows = [
      ["Wall time", $formatter->formatInterval((int) $wall_seconds)],
      ["Test discovery time", $formatter->formatInterval((int) $discovery_seconds)],
    ];
    if (isset($overall_test_time)) {
      $rows[] = ["Total testing time", $formatter->formatInterval((int) round($overall_test_time))];
    }
    $rows[] = ["Total execution time", $formatter->formatInterval((int) $total_time)];
    if ($this->config->get('concurrency') > 1 && $wall_seconds > 0) {
      $rows[] = [
        "Speedup",
        sprintf("%.2fx (concurrency %d)", $total_time / ($wall_seconds - $discovery_seconds), $this->config->get('concurrency')),
      ];
    }

    $this->io->createTable()
      ->setRows($rows)
      ->render();
  }

  /**
   * Print help text.
   */
  public function help(InputDefinition $input_definition, string $script_basename, StyleInterface $io): void {

    $this->io->title('Run Drupal tests from the shell.');

    $this->io->writeln(<<<EOF
Usage:        {$script_basename} [OPTIONS] <tests>
Example:      {$script_basename} Profile

EOF
    );

    $helper = new DescriptorHelper();
    $helper->describe($io, $input_definition);

    $this->io->writeln(<<<EOF


To run this script you will normally invoke it from the root directory of your
Drupal installation as the webserver user (differs per configuration), or root:

sudo -u [wwwrun|www-data|etc] php ./core/scripts/{$script_basename} --url http://example.com/ --all

sudo -u [wwwrun|www-data|etc] php ./core/scripts/{$script_basename} --url http://example.com/ --class Drupal\\\\Tests\\\\block\\\\Functional\\\\BlockTest

Without a preinstalled Drupal site, specify a SQLite database pathname to create
(for the test runner) and the default database connection info (for Drupal) to
use in tests:

sudo -u [wwwrun|www-data|etc] php ./core/scripts/{$script_basename}
  --sqlite /tmpfs/drupal/test.sqlite
  --dburl mysql://username:password@localhost/database
  --url http://example.com/ --all


EOF
    );
  }

}
