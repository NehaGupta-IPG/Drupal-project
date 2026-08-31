<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Config\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigLangcodeContext;
use Drupal\Core\Config\ConfigLangcodeResolveEvent;
use Drupal\Core\Config\EventSubscriber\ConfigLangcodeSiteDefaultSubscriber;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests \Drupal\Core\Config\EventSubscriber\ConfigLangcodeSiteDefaultSubscriber.
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
   * Tests a missing runtime langcode resolves to the site default.
   */
  public function testRuntimeMissingLangcodeResolvesToSiteDefault(): void {
    $event = new ConfigLangcodeResolveEvent('config_test.dynamic.foo', NULL, ConfigLangcodeContext::Runtime);
    $this->subscriberWithSiteDefault('fr')->onResolveLangcode($event);
    $this->assertSame('fr', $event->getLangcode());
    $this->assertTrue($event->isLangcodeSupported('fr'));
  }

  /**
   * Tests a missing or English install-time langcode resolves to the default.
   */
  public function testInstallMissingOrEnglishLangcodeResolvesToSiteDefault(): void {
    $event = new ConfigLangcodeResolveEvent('config_test.settings', NULL, ConfigLangcodeContext::ExtensionInstall);
    $this->subscriberWithSiteDefault('fr')->onResolveLangcode($event);
    $this->assertSame('fr', $event->getLangcode());

    $event = new ConfigLangcodeResolveEvent('config_test.settings', 'en', ConfigLangcodeContext::ExtensionInstall);
    $this->subscriberWithSiteDefault('fr')->onResolveLangcode($event);
    $this->assertSame('fr', $event->getLangcode());
  }

  /**
   * Tests an explicit, unrelated install-time langcode is left alone.
   */
  public function testInstallExplicitOtherLangcodeIsUntouched(): void {
    $event = new ConfigLangcodeResolveEvent('config_test.settings', 'de', ConfigLangcodeContext::ExtensionInstall);
    $this->subscriberWithSiteDefault('fr')->onResolveLangcode($event);
    $this->assertSame('de', $event->getLangcode());
    // The site default is still vouched for, even though this configuration
    // was not resolved to it.
    $this->assertTrue($event->isLangcodeSupported('fr'));
  }

  /**
   * Tests an already-resolved langcode is never second-guessed.
   *
   * This is the mechanism a module establishing its own default-langcode
   * policy - for example always defaulting new configuration to English
   * regardless of the site default - relies on: it resolves the langcode
   * itself, from a higher-priority subscriber, without needing to stop
   * propagation.
   *
   * @see https://git.drupalcode.org/project/config_language_lock/-/work_items/3613944
   */
  public function testAlreadyResolvedLangcodeIsNotOverridden(): void {
    // Runtime: a policy subscriber already chose 'en' for configuration
    // that had no langcode of its own.
    $event = new ConfigLangcodeResolveEvent('config_test.dynamic.foo', NULL, ConfigLangcodeContext::Runtime);
    $event->setLangcode('en');
    $this->subscriberWithSiteDefault('fr')->onResolveLangcode($event);
    $this->assertSame('en', $event->getLangcode());

    // ExtensionInstall: a policy subscriber deliberately resolved shipped
    // configuration back to the English it already had - indistinguishable
    // from "untouched" by value alone, which is exactly why this needs to
    // be tracked explicitly rather than inferred by comparison.
    $event = new ConfigLangcodeResolveEvent('config_test.settings', 'en', ConfigLangcodeContext::ExtensionInstall);
    $event->setLangcode('en');
    $this->subscriberWithSiteDefault('fr')->onResolveLangcode($event);
    $this->assertSame('en', $event->getLangcode());
  }

  /**
   * Tests the site default is vouched for even when outvoted by a policy.
   */
  public function testSiteDefaultStillVouchedForWhenOutvoted(): void {
    $event = new ConfigLangcodeResolveEvent('config_test.dynamic.foo', NULL, ConfigLangcodeContext::Runtime);
    $event->setLangcode('en');
    $this->subscriberWithSiteDefault('fr')->onResolveLangcode($event);
    $this->assertTrue($event->isLangcodeSupported('fr'));
  }

}
