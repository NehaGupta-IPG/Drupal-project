<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Consolidates multiple deprecation statistics files within a CI job.
 *
 * CI environments produce one deprecation file per test run; this class
 * consolidates them all in one merged file, adding the job context as
 * metadata.
 *
 * @internal
 */
final class CiJobStatisticsConsolidator {

  /**
   * This class should not be instantiated.
   */
  public function __construct(
    private array $jobInfo,
    private array $jobContext,
  ) {
  }

  public function process(
    string $inputArtifactsPath,
    string $outputFile,
  ): void {
    $finder = new Finder();

    $workDir = $inputArtifactsPath . \DIRECTORY_SEPARATOR . 'deprecation-handler' . \DIRECTORY_SEPARATOR . 'jobs' . \DIRECTORY_SEPARATOR . $this->jobInfo['CI_JOB_ID'];

    $files = $finder->files()->in($workDir)->name('deprecation-ignore-stats*.json');

    $tmp = [];
    $tmp_test = [];
    foreach ($files as $file) {
      $contents = json_decode($file->getContents(), TRUE);
      foreach ($contents['skips'] as $key => $count) {
        $tmp[$key] = isset($tmp[$key]) ? $tmp[$key] + $count : $count;
      }
      foreach ($contents['tests'] as $testId => $data) {
        if (isset($tmp_test[$testId])) {
          throw new \RuntimeException($testId . " is already consolidated");
        }
        $tmp_test[$testId] = $data;
      }
      unlink($file->getPathName());
    }

    file_put_contents($outputFile, json_encode([
      'metadata' => array_merge($this->jobInfo, $this->jobContext),
      'skips' => $tmp,
      'tests' => $tmp_test,
    ]));

    $filesystem = new Filesystem();
    $filesystem->remove($inputArtifactsPath . \DIRECTORY_SEPARATOR . 'deprecation-handler');
  }

}
