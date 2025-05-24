<?php

declare(strict_types=1);

namespace Drupal\hook_collector_hook_attribute\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\hook_collector_hook_attribute\HookInvocationsCountTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Test Hook class that is also a declared service.
 *
 * This class is registered as a service in
 * hook_collector_hook_attribute.services.yml. It also exists in the Hook
 * sublevel in this module's namespace, so it should automatically be picked up
 * by the HookCollectorPass without being registered in the services.yml This is
 * used to test that the hook implementation does not get invoked more than once.
 */
#[Hook('test_hook_1')]
class HookRegisteredService {

  use HookInvocationsCountTrait;

  public function __construct(
    #[Autowire(service: 'keyvalue')]
    protected KeyValueFactoryInterface $keyValueFactory,
  ) {}

  /**
   * Implements hook_test_hook_1().
   */
  public function __invoke(): void {
    $this->incrementRunCount(get_class($this) . '::' . __FUNCTION__);
  }

}
