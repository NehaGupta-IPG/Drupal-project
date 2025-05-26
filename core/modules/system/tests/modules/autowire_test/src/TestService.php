<?php

declare(strict_types=1);

namespace Drupal\autowire_test;

use Drupal\Core\Database\Connection;
use Drupal\Core\DrupalKernelInterface;

/**
 * Service class for managing injected dependencies.
 */
class TestService {

  public function __construct(
    protected TestInjectionInterface $testInjection,
    protected TestInjection2 $testInjection2,
    /**
     * The database connection.
     */
    protected Connection $database,
    /**
     * The Drupal kernel.
     */
    protected DrupalKernelInterface $kernel,
    protected TestInjectionInterface $testInjection3,
  ) {
  }

  public function getTestInjection(): TestInjectionInterface {
    return $this->testInjection;
  }

  public function getTestInjection2(): TestInjection2 {
    return $this->testInjection2;
  }

  public function getTestInjection3(): TestInjection3 {
    return $this->testInjection3;
  }

  public function getDatabase(): Connection {
    return $this->database;
  }

  public function getKernel(): DrupalKernelInterface {
    return $this->kernel;
  }

}
