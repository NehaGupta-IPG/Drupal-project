<?php

declare(strict_types=1);

namespace Drupal\hook_collector_altered_services;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\hook_collector_hook_attribute\HookInvocationsCountTrait;
use Drupal\hook_collector_hook_attribute\ServiceWithHook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service that decorates ServiceWithHook and has hook implementations.
 */
class DecoratedServiceWithHook {

  use HookInvocationsCountTrait;

  public function __construct(
    protected readonly ServiceWithHook $inner,
    #[Autowire(service: 'keyvalue')]
    protected KeyValueFactoryInterface $keyValueFactory,
  ) {}

  /**
   * Implements hook_test_hook_2().
   */
  #[Hook('test_hook_2')]
  public function testHook2(): void {
    // There is a test_hook_2 implementation in ServiceWithHook, but that should
    // not run and this one should when this class's module is installed.
    $this->incrementRunCount(get_class($this) . '::' . __FUNCTION__);
  }

  /**
   * Implements hook_test_hook_4().
   */
  #[Hook('test_hook_4')]
  public function testHook4(): void {
    $this->incrementRunCount(get_class($this) . '::' . __FUNCTION__);
  }

}
