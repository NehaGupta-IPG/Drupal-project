<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Config\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigLangcodeResolveEvent;
use Drupal\Core\Config\EventSubscriber\ConfigLangcodeSiteDefaultSubscriber;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests \Drupal\Core\Config\EventSubscriber\ConfigLangcodeSiteDefaultSubscriber.
 *
 * Precedence against a higher-priority subscriber is not testable here in
 * isolation: it depends on a real event dispatcher stopping propagation
 * before this subscriber is even invoked, not on anything this class does
 * itself.
 *
 * @see \Drupal\KernelTests\Core\Config\ConfigLangcodeFirstResolverWinsTest
 */
#[CoversClass(ConfigLangcodeSiteDefaultSubscriber::class)]
#[Group('config')]
class ConfigLangcodeSiteDefaultSubscriberTest extends UnitTestCase {

  /**
   * Builds a subscriber whose site default langcode is $default_langcode.
   */
  protected function subscriberWithSiteDefault(string $default_langcode): ConfigLangcodeSiteDefaultSubscriber {
    $system_site = $this->createMock(ImmutableConfig::class);
    $system_site->method('get')->with('default_langcode')->willReturn($default_langcode);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('system.site')->willReturn($system_site);
    return new ConfigLangcodeSiteDefaultSubscriber($config_factory);
  }

  /**
   * Tests the event resolves to the site default.
   */
  public function testResolvesToSiteDefault(): void {
    $event = new ConfigLangcodeResolveEvent();
    $this->subscriberWithSiteDefault('fr')->onResolveLangcode($event);
    $this->assertSame('fr', $event->getLangcode());
    $this->assertTrue($event->isPropagationStopped());
  }

}
