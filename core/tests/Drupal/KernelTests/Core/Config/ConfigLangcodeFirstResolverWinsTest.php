<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Config;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigLangcodeContext;
use Drupal\Core\Config\ConfigLangcodeResolveEvent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests a higher-priority RESOLVE_LANGCODE subscriber can outvote the default.
 *
 * Simulates a module establishing its own default-langcode policy -
 * for example always defaulting new configuration to English regardless of
 * the site default - the scenario behind
 * https://git.drupalcode.org/project/config_language_lock/-/work_items/3613944.
 * That policy has to coexist with core's own subscribers: it must win over
 * \Drupal\Core\Config\EventSubscriber\ConfigLangcodeSiteDefaultSubscriber
 * without stopping propagation, since stopping propagation would also
 * prevent language module from vouching for the site's configured
 * languages.
 *
 * @see \Drupal\Core\Config\ConfigLangcodeResolveEvent::setLangcode()
 */
#[Group('config')]
#[RunTestsInSeparateProcesses]
class ConfigLangcodeFirstResolverWinsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'language', 'config_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'language']);
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->config('system.site')->set('default_langcode', 'fr')->save();
  }

  /**
   * Registers a listener that always resolves to English, without stopping.
   */
  protected function registerLockToEnglishPolicy(): void {
    \Drupal::service('event_dispatcher')->addListener(
      ConfigEvents::RESOLVE_LANGCODE,
      function (ConfigLangcodeResolveEvent $event): void {
        if ($event->getCurrentLangcode() === NULL || $event->getCurrentLangcode() === 'en') {
          $event->setLangcode('en');
        }
      },
      // Above ConfigLangcodeSiteDefaultSubscriber's -10000, so this gets
      // first refusal.
      100
    );
  }

  /**
   * Tests the policy wins for a config entity created without a langcode.
   */
  public function testPolicyWinsForConfigEntityCreation(): void {
    $this->registerLockToEnglishPolicy();

    $entity = \Drupal::entityTypeManager()->getStorage('config_test')->create([
      'id' => 'policy_locked',
      'label' => 'Policy locked',
    ]);
    $entity->save();

    // The policy's choice survives, even though the site default is 'fr'
    // and nothing stopped propagation.
    $this->assertSame('en', $entity->language()->getId());
  }

  /**
   * Tests the policy wins for shipped configuration installed at 'en'.
   */
  public function testPolicyWinsForExtensionInstall(): void {
    $this->registerLockToEnglishPolicy();

    $event = new ConfigLangcodeResolveEvent('en', ConfigLangcodeContext::ExtensionInstall);
    \Drupal::service('event_dispatcher')->dispatch($event, ConfigEvents::RESOLVE_LANGCODE);

    $this->assertSame('en', $event->getLangcode());
    // The site default was still vouched for, even though this
    // configuration was not resolved to it.
    $this->assertTrue($event->isLangcodeSupported('fr'));
  }

  /**
   * Tests the site default still wins when the policy has no opinion.
   */
  public function testSiteDefaultAppliesWhenPolicyDoesNotAct(): void {
    $this->registerLockToEnglishPolicy();

    // Explicitly French: the policy above only ever acts on NULL or 'en',
    // so this is left for the site-default subscriber to leave alone too -
    // an explicit, already-supported langcode is never overwritten.
    $event = new ConfigLangcodeResolveEvent('fr', ConfigLangcodeContext::ExtensionInstall);
    \Drupal::service('event_dispatcher')->dispatch($event, ConfigEvents::RESOLVE_LANGCODE);
    $this->assertSame('fr', $event->getLangcode());
    $this->assertTrue($event->isLangcodeSupported('fr'));
  }

}
