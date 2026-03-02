#!/usr/bin/env php
<?php

/**
 * @file
 * Consolidate statistics across different CI jobs.
 */

use Symfony\Component\Finder\Finder;

function trim_with_ellipsis(string $input, int $length, int $side): string {
    if (strlen($input) < $length) {
        return str_pad($input, $length, ' ', STR_PAD_RIGHT);
    }
    elseif (strlen($input) > $length) {
        return match($side) {
          STR_PAD_RIGHT => substr($input, 0, $length - 3) . '...',
          default => '...' . substr($input, -$length + 3),
        };
    }
    return $input;
}

// -----------------------------------------------------------------

if (PHP_SAPI !== 'cli') {
  return;
}

$args = $_SERVER['argv'];

if (!isset($args[1])) {
  echo "Artifacts path not specified.\n";
  exit(2);
}

// PHP major/minor version.
$phpImage = $args[2] ?? NULL;
if ($phpImage) {
  preg_match('/^\d+\.\d+/', $phpImage, $matches);
  $phpVersion = $matches[0];
}

require __DIR__ . '/../../vendor/autoload.php';

$finder = new Finder();

$files = $finder->files()->in($args[1])->name('deprecation-ignore-stats.json');

$tmp = [];
$tmp_test = [];
foreach ($files as $file) {
  $contents = json_decode($file->getContents(), TRUE);
  dump([$file->getRealPath(), $contents['metadata'], $contents['metadata']['php_version'] === $phpVersion ? 'MATCH' : '**MISS**']);
  if ($contents['metadata']['php_version'] !== $phpVersion) {
    continue;
  }
  foreach ($contents['skips'] as $key => $count) {
    $tmp[$key] = isset($tmp[$key]) ? $tmp[$key] + $count : $count;
  }
  foreach ($contents['tests'] as $key => $count) {
    $tmp_test[$key] = $count;
  }
}

$outputFile = $args[1] . \DIRECTORY_SEPARATOR . "deprecation-ignore-stats.json";
file_put_contents($outputFile, json_encode($tmp));

echo "\n\nDeprecation skips statistics\n";
echo "-----------+----------------------------------------------------------------------\n";
echo "    Skips# | Line\n";
echo "-----------+----------------------------------------------------------------------\n";
foreach (file(__DIR__ . '/../../core' . \DIRECTORY_SEPARATOR . '.deprecation-ignore.txt') as $line => $pattern) {
  if ((trim($pattern)[0] ?? '#') !== '#') {
    $msg = "%10d | %s";
    if (($tmp[$pattern] ?? 0) === 0) {
      $msg = "\033[" . "33" . "m" . $msg . "\033[0m";
    }
    echo sprintf($msg . "\n", $tmp[$pattern] ?? 0, trim_with_ellipsis(rtrim($pattern, "\n"), 80, \STR_PAD_RIGHT));
  }
  else {
    echo sprintf("           | %s\n", trim_with_ellipsis(rtrim($pattern, "\n"), 80, \STR_PAD_RIGHT));
  }
}
echo "-----------+----------------------------------------------------------------------\n";

echo "\n\nTests ignoring deprecations\n";
echo "-----------+----------------------------------------------------------------------\n";
dump($tmp_test);
echo "-----------+----------------------------------------------------------------------\n";
