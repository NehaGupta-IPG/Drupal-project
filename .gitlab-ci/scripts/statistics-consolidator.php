#!/usr/bin/env php
<?php

/**
 * @file
 * Consolidate statistics across different CI jobs.
 */

use Symfony\Component\Finder\Finder;
 
if (PHP_SAPI !== 'cli') {
  return;
}

require __DIR__ . '/../../vendor/autoload.php';

$finder = new Finder();

$files = $finder->files()->in(__DIR__ . '/../../test-artifacts')->name('deprecation-ignore-stats.json');

$tmp = [];
foreach ($files as $file) {
  $contents = json_decode($file->getContents(), TRUE);
dump($contents);
  $metadata = $contents['metadata'];
  foreach ($contents['skips'] as $key => $count) {
    $tmp[$key] = isset($tmp[$key]) ? $tmp[$key] + $count : $count;
  }
}

$outputFile = __DIR__ . '/../../test-artifacts' . \DIRECTORY_SEPARATOR . "deprecation-ignore-stats.json";
file_put_contents($outputFile, json_encode($tmp));

echo "\n\nDeprecation skips statistics\n";
echo "-----------+----------------------------------------------------------------------\n";
echo "    Skips# | Line\n";
echo "-----------+----------------------------------------------------------------------\n";
foreach (file(__DIR__ . '/../../core' . \DIRECTORY_SEPARATOR . '.deprecation-ignore.txt') as $line => $pattern) {
  if ((trim($pattern)[0] ?? '#') !== '#') {
    echo sprintf("%10d | %s\n", $tmp[$pattern] ?? 0, trim_with_ellipsis(rtrim($pattern, "\n"), 80, \STR_PAD_RIGHT));
  }
  else {
    echo sprintf("           | %s\n", trim_with_ellipsis(rtrim($pattern, "\n"), 80, \STR_PAD_RIGHT));
  }
}
echo "-----------+----------------------------------------------------------------------\n";
