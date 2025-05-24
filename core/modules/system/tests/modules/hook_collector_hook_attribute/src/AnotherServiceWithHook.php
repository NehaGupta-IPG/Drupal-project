<?php

declare(strict_types=1);

namespace Drupal\hook_collector_hook_attribute;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Another service class with Hook attribute.
 *
 * This is used to test that any service registered in the module's services.yml
 * can have hook implementations, regardless of where under the module's
 * namespace it exists.
 */
class AnotherServiceWithHook {

  use HookInvocationsCountTrait;

  public function __construct(
    #[Autowire(service: 'keyvalue')]
    protected KeyValueFactoryInterface $keyValueFactory,
  ) {}

  /**
   * Implements hook_test_hook_3().
   */
  #[Hook('test_hook_3')]
  public function testHook3(): void {
    $this->incrementRunCount(get_class($this) . '::' . __FUNCTION__);
  }

  /**
   * Implements hook_test_hook_6().
   */
  #[Hook('test_hook_6')]
  public function testHook6(): void {
    $this->incrementRunCount(get_class($this) . '::' . __FUNCTION__);
  }

}
