<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Config;

use Drupal\Core\Config\ConfigLangcodeResolveEvent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests a ConfigLangcodeResolveEvent subscriber can override the default.
 *
 * Simulates a module establishing its own default-langcode policy, for example,
 * always defaulting new configuration to English regardless of the site
 * default. That policy has to coexist with core's own subscribers: it must win
 * over \Drupal\Core\Config\EventSubscriber\ConfigLangcodeSiteDefaultSubscriber
 * by running first and stopping propagation.
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
   * Registers a listener resolving to German when it has an opinion.
   *
   * @param bool $should_act
   *   Whether the policy has an opinion this time.
   */
  protected function registerLockToGermanPolicy(bool $should_act = TRUE): void {
    \Drupal::service('event_dispatcher')->addListener(
      ConfigLangcodeResolveEvent::class,
      function (ConfigLangcodeResolveEvent $event) use ($should_act): void {
        if ($should_act) {
          $event->setLangcode('de');
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
    $this->registerLockToGermanPolicy();

    $entity = \Drupal::entityTypeManager()->getStorage('config_test')->create([
      'id' => 'policy_locked',
      'label' => 'Policy locked',
    ]);
    $entity->save();

    // The policy's choice survives, even though the site default is 'fr' -
    // stopping propagation kept ConfigLangcodeSiteDefaultSubscriber from
    // ever running.
    $this->assertSame('de', $entity->language()->getId());
  }

  /**
   * Tests the policy wins over the site default on a bare dispatch.
   */
  public function testPolicyWinsOverSiteDefault(): void {
    $this->registerLockToGermanPolicy();

    $event = new ConfigLangcodeResolveEvent();
    \Drupal::service('event_dispatcher')->dispatch($event);

    $this->assertSame('de', $event->getLangcode());
  }

  /**
   * Tests the site default still wins when the policy has no opinion.
   */
  public function testSiteDefaultAppliesWhenPolicyDoesNotAct(): void {
    $this->registerLockToGermanPolicy(FALSE);

    $event = new ConfigLangcodeResolveEvent();
    \Drupal::service('event_dispatcher')->dispatch($event);
    $this->assertSame('fr', $event->getLangcode());
  }

}
