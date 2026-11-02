<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Config\EventSubscriber;

use Drupal\Core\Config\ConfigLangcodeResolveEvent;
use Drupal\Core\Config\EventSubscriber\ConfigLangcodeSiteDefaultSubscriber;
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
   * Tests the event resolves to the site default.
   */
  public function testResolvesToSiteDefault(): void {
    $config_factory = $this->getConfigFactoryStub(['system.site' => ['default_langcode' => 'fr']]);
    $event = new ConfigLangcodeResolveEvent();
    (new ConfigLangcodeSiteDefaultSubscriber($config_factory))->onResolveLangcode($event);
    $this->assertSame('fr', $event->getLangcode());
    $this->assertTrue($event->isPropagationStopped());
  }

}
