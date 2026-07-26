#!/usr/bin/env php
<?php

/**
 * @file
 * Consolidate statistics within a CI job.
 */

use Symfony\Component\Finder\Finder;

if (PHP_SAPI !== 'cli') {
  return;
}

$args = $_SERVER['argv'];

if (!isset($args[1])) {
  echo "Artifacts path not specified.\n";
  exit(2);
}

if (!isset($args[2])) {
  echo "Consolidation file not specified.\n";
  exit(2);
}

require __DIR__ . '/../../vendor/autoload.php';

$finder = new Finder();

$files = $finder->files()->in($args[1])->name('deprecation-ignore-stats*.json');

$tmp = [];
$tmp_test = [];
$metadata = NULL;
foreach ($files as $file) {
  $contents = json_decode($file->getContents(), TRUE);
  $metadata = $contents['metadata'];
  foreach ($contents['skips'] as $key => $count) {
    $tmp[$key] = isset($tmp[$key]) ? $tmp[$key] + $count : $count;
  }
  foreach ($contents['tests'] as $testId => $data) {
    if ($data['ignoreDeprecations'] || isset($data['reported'])) {
      $tmp_test[$testId] = $data;
    }
  }
}

file_put_contents($args[2], json_encode([
  'metadata' => $metadata,
  'skips' => $tmp,
  'tests' => $tmp_test,
]));

exit(0);
