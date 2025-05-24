<?php

declare(strict_types=1);

namespace Drupal\hook_collector_hook_attribute;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Trait used to track how often a hook implementation method has run.
 */
trait HookInvocationsCountTrait {

  /**
   * Array of how often methods have run, indexed by method name.
   *
   * @var array{string, int}
   */
  protected array $countInvocations = [];

  /**
   * The key value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected KeyValueFactoryInterface $keyValueFactory;

  /**
   * Increments the run counter for a method.
   *
   * @param string $method
   *   The method name.
   */
  protected function incrementRunCount(string $method): void {
    $this->countInvocations[$method] ??= 0;
    $this->countInvocations[$method]++;
    $keyValue = $this->keyValueFactory->get('test_service_hooks');
    $runCounts = $keyValue->get('run_counts', []);
    $runCounts[$method] = $this->countInvocations[$method];
    $keyValue->set('run_counts', $runCounts);
  }

}
