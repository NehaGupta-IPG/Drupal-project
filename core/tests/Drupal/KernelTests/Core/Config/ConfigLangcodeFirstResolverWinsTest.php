<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Config;

use Drupal\Core\Config\ConfigEvents;
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
 * \Drupal\Core\Config\EventSubscriber\ConfigLangcodeSiteDefaultSubscriber by
 * running first and stopping propagation, the same way core's own installer
 * subscriber already does.
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
   * Registers a listener resolving to English when it has an opinion.
   *
   * @param bool $should_act
   *   Whether the policy has an opinion this time. A real policy would base
   *   this on its own configuration rather than a parameter, but the effect
   *   on precedence is the same either way: acting resolves the langcode
   *   and stops propagation, leaving nothing for a later subscriber to do.
   */
  protected function registerLockToEnglishPolicy(bool $should_act = TRUE): void {
    \Drupal::service('event_dispatcher')->addListener(
      ConfigEvents::RESOLVE_LANGCODE,
      function (ConfigLangcodeResolveEvent $event) use ($should_act): void {
        if ($should_act) {
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

    // The policy's choice survives, even though the site default is 'fr' -
    // stopping propagation kept ConfigLangcodeSiteDefaultSubscriber from
    // ever running.
    $this->assertSame('en', $entity->language()->getId());
  }

  /**
   * Tests the policy wins over the site default on a bare dispatch.
   */
  public function testPolicyWinsOverSiteDefault(): void {
    $this->registerLockToEnglishPolicy();

    $event = new ConfigLangcodeResolveEvent();
    \Drupal::service('event_dispatcher')->dispatch($event, ConfigEvents::RESOLVE_LANGCODE);

    $this->assertSame('en', $event->getLangcode());
  }

  /**
   * Tests the site default still wins when the policy has no opinion.
   */
  public function testSiteDefaultAppliesWhenPolicyDoesNotAct(): void {
    $this->registerLockToEnglishPolicy(FALSE);

    $event = new ConfigLangcodeResolveEvent();
    \Drupal::service('event_dispatcher')->dispatch($event, ConfigEvents::RESOLVE_LANGCODE);
    $this->assertSame('fr', $event->getLangcode());
  }

}
