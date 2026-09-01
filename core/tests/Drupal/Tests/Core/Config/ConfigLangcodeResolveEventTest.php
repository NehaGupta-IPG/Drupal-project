<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Config;

use Drupal\Core\Config\ConfigLangcodeResolveEvent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests \Drupal\Core\Config\ConfigLangcodeResolveEvent.
 */
#[CoversClass(ConfigLangcodeResolveEvent::class)]
#[Group('config')]
class ConfigLangcodeResolveEventTest extends UnitTestCase {

  /**
   * Tests reading the langcode before it is resolved fails loudly.
   *
   * There is no meaningful "unresolved" value to return instead - every
   * caller of getLangcode() is entitled to assume dispatching the event
   * already produced one, since core.services.yml always registers a
   * subscriber that resolves unconditionally.
   */
  public function testLangcodeMustBeResolvedBeforeReading(): void {
    $event = new ConfigLangcodeResolveEvent();
    $this->expectException(\Error::class);
    $event->getLangcode();
  }

  /**
   * Tests setLangcode() resolves the langcode and stops propagation.
   *
   * Stopping propagation is what a real dispatch relies on to give a
   * higher-priority, more specific subscriber precedence over a later, more
   * general one - for example a module always defaulting new configuration
   * to English regardless of the site default: once it resolves, dispatch()
   * never calls a later, lower-priority subscriber at all.
   *
   * @see https://git.drupalcode.org/project/config_language_lock/-/work_items/3613944
   */
  public function testSetLangcodeResolvesAndStopsPropagation(): void {
    $event = new ConfigLangcodeResolveEvent();
    $this->assertFalse($event->isPropagationStopped());

    $event->setLangcode('en');

    $this->assertSame('en', $event->getLangcode());
    $this->assertTrue($event->isPropagationStopped());
  }

}
