<?php

declare(strict_types=1);

namespace Drupal\autowire_test;

use Drupal\Core\Database\Connection;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Service class for managing injected dependencies.
 */
class TestService {

  /**
   * @var \Drupal\autowire_test\TestInjectionInterface
   */
  protected $testInjection;

  /**
   * @var \Drupal\autowire_test\TestInjection2
   */
  protected $testInjection2;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Drupal kernel.
   *
   * @var \Drupal\Core\DrupalKernelInterface
   */
  protected $kernel;

  /**
   * Current user.
   */
  protected AccountProxyInterface $currentUser;

  public function __construct(TestInjectionInterface $test_injection, TestInjection2 $test_injection2, Connection $database, DrupalKernelInterface $kernel, protected TestInjectionInterface $testInjection3, AccountProxyInterface $currentUser) {
    $this->testInjection = $test_injection;
    $this->testInjection2 = $test_injection2;
    $this->database = $database;
    $this->kernel = $kernel;
    $this->currentUser = $currentUser;
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

  public function getCurrentUser(): AccountProxyInterface {
    return $this->currentUser;
  }

}
