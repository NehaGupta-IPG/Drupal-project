<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Config;

use Drupal\Core\Config\ConfigLangcodeContext;
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
   * Tests the langcode is seeded from, and defaults to, the current one.
   */
  public function testLangcodeSeededFromCurrent(): void {
    $event = new ConfigLangcodeResolveEvent('fr', ConfigLangcodeContext::Runtime);
    $this->assertSame('fr', $event->getCurrentLangcode());
    $this->assertSame('fr', $event->getLangcode());
  }

  /**
   * Tests setLangcode() succeeds even when setting the same value.
   */
  public function testSetLangcodeSucceedsForSameValue(): void {
    $event = new ConfigLangcodeResolveEvent('en', ConfigLangcodeContext::ExtensionInstall);

    // Setting the langcode to the value it already had must still count as
    // resolving it: a subscriber locking configuration to English needs
    // this to work the same whether the configuration already happened to
    // be English or not.
    $this->assertTrue($event->setLangcode('en'));
    $this->assertSame('en', $event->getLangcode());
    // A second call, even to the same value again, no longer succeeds.
    $this->assertFalse($event->setLangcode('en'));
  }

  /**
   * Tests the first setLangcode() call wins; a second call is ignored.
   *
   * This is the mechanism a subscriber establishing its own default-langcode
   * policy relies on - for example always defaulting new configuration to
   * English regardless of the site default: it resolves the langcode from a
   * higher-priority subscriber, and a later, more general subscriber (such
   * as core's own site-default one) cannot overwrite that choice, without
   * either of them needing to stop propagation.
   *
   * @see https://git.drupalcode.org/project/config_language_lock/-/work_items/3613944
   */
  public function testSecondSetLangcodeCallIsIgnored(): void {
    $event = new ConfigLangcodeResolveEvent(NULL, ConfigLangcodeContext::Runtime);
    $this->assertTrue($event->setLangcode('en'));
    $this->assertSame('en', $event->getLangcode());

    $this->assertFalse($event->setLangcode('fr'));
    $this->assertSame('en', $event->getLangcode());
  }

  /**
   * Tests a langcode is only supported once vouched for.
   */
  public function testLangcodeSupportRequiresVouching(): void {
    $event = new ConfigLangcodeResolveEvent(NULL, ConfigLangcodeContext::ExtensionInstall);
    $this->assertFalse($event->isLangcodeSupported('fr'));

    $event->allowLangcode('fr');
    $this->assertTrue($event->isLangcodeSupported('fr'));
    $this->assertFalse($event->isLangcodeSupported('de'));

    $event->allowLangcodes(['de', 'es']);
    $this->assertTrue($event->isLangcodeSupported('de'));
    $this->assertTrue($event->isLangcodeSupported('es'));
  }

}
