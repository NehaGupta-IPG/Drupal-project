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
   * There is no meaningful "unresolved" value. Every caller of getLangcode() is
   * entitled to assume dispatching the event will result in a valid langcode.
   * Core registers ConfigLangcodeSiteDefaultSubscriber to ensure a langcode is
   * resolved unconditionally.
   *
   * @see \Drupal\Core\Config\EventSubscriber\ConfigLangcodeSiteDefaultSubscriber
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
   * general one.
   */
  public function testSetLangcodeResolvesAndStopsPropagation(): void {
    $event = new ConfigLangcodeResolveEvent();
    $this->assertFalse($event->isPropagationStopped());

    $event->setLangcode('en');

    $this->assertSame('en', $event->getLangcode());
    $this->assertTrue($event->isPropagationStopped());
  }

}
