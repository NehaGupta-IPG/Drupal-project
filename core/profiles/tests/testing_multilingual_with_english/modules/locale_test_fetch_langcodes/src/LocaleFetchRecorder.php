<?php

declare(strict_types=1);

namespace Drupal\locale_test_fetch_langcodes;

use Drupal\locale\LocaleFetch;

/**
 * Decorates LocaleFetch to record langcodes passed to buildUpdateBatch.
 */
class LocaleFetchRecorder extends LocaleFetch {

  public function __construct(
    protected readonly LocaleFetch $inner,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildUpdateBatch(array $projects = [], array $langcodes = [], array $options = []): array {
    \Drupal::state()->set('locale_test_fetch_langcodes.langcodes', $langcodes);
    return $this->inner->buildUpdateBatch($projects, $langcodes, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function buildFetchBatch(array $projects = [], array $langcodes = [], array $options = []): array {
    return $this->inner->buildFetchBatch($projects, $langcodes, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatusOperations(array $projects, array $langcodes, array $options = []): array {
    return $this->inner->getStatusOperations($projects, $langcodes, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function batchDownload(string $project, string $langcode, array|\ArrayAccess &$context): void {
    $this->inner->batchDownload($project, $langcode, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function batchImport(string $project, string $langcode, array $options, array|\ArrayAccess &$context): void {
    $this->inner->batchImport($project, $langcode, $options, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function batchFinished(bool $success, array $results): void {
    $this->inner->batchFinished($success, $results);
  }

  /**
   * {@inheritdoc}
   */
  public function batchVersionCheck(string $project, string $langcode, array|\ArrayAccess &$context): void {
    $this->inner->batchVersionCheck($project, $langcode, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function batchStatusCheck(string $project, string $langcode, array $options, array|\ArrayAccess &$context): void {
    $this->inner->batchStatusCheck($project, $langcode, $options, $context);
  }

}
