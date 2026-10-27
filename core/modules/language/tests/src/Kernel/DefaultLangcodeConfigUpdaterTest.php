<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\DefaultLangcodeConfigUpdater;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Event\DefaultLangcodeConfigReconcileEvent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the batch operation that reconciles config with a new default langcode.
 *
 * @see \Drupal\language\DefaultLangcodeConfigUpdater
 */
#[Group('language')]
#[RunTestsInSeparateProcesses]
class DefaultLangcodeConfigUpdaterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language', 'config_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();
  }

  /**
   * Tests configuration with the previous default is updated.
   */
  public function testConfigWithOldDefaultIsUpdated(): void {
    $this->config('config_test.langcode_test')
      ->set('optional_translatable_value', 'Hello')
      ->set('langcode', 'en')
      ->save();

    $context = $this->reconcile('en', 'fr');

    $this->assertSame('fr', $this->config('config_test.langcode_test')->get('langcode'));
    $this->assertContains('config_test.langcode_test', $context['results']['updated_names']);
  }

  /**
   * Tests configuration with a third langcode is untouched.
   */
  public function testConfigWithThirdLangcodeIsUntouched(): void {
    $this->config('config_test.langcode_test')
      ->set('optional_translatable_value', 'Hallo')
      ->set('langcode', 'de')
      ->save();

    $context = $this->reconcile('en', 'fr');

    $this->assertSame('de', $this->config('config_test.langcode_test')->get('langcode'));
    $this->assertSame('Hallo', $this->config('config_test.langcode_test')->get('optional_translatable_value'));
    $this->assertNotContains('config_test.langcode_test', $context['results']['updated_names']);
  }

  /**
   * Tests a config entity without an explicit langcode is updated.
   */
  public function testConfigEntityIsUpdated(): void {
    \Drupal::entityTypeManager()->getStorage('config_test')->create([
      'id' => 'no_explicit_langcode',
      'label' => 'No explicit langcode',
    ])->save();
    $this->assertSame('en', $this->config('config_test.dynamic.no_explicit_langcode')->get('langcode'));

    $this->reconcile('en', 'fr');

    $this->assertSame('fr', $this->config('config_test.dynamic.no_explicit_langcode')->get('langcode'));
  }

  /**
   * Tests a subscriber can skip configuration.
   */
  public function testEventSkipsConfiguration(): void {
    $this->config('config_test.langcode_test')
      ->set('optional_translatable_value', 'Hello')
      ->save();
    $listener = function (DefaultLangcodeConfigReconcileEvent $event): void {
      if ($event->name === 'config_test.langcode_test') {
        $event->skip();
      }
    };
    \Drupal::service('event_dispatcher')->addListener(DefaultLangcodeConfigReconcileEvent::class, $listener);

    $context = $this->reconcile('en', 'fr');

    $this->assertSame('en', $this->config('config_test.langcode_test')->get('langcode'));
    $this->assertNotContains('config_test.langcode_test', $context['results']['updated_names'] ?? []);
    // No override is saved for skipped configuration.
    $override = \Drupal::languageManager()->getLanguageConfigOverride('en', 'config_test.langcode_test');
    $this->assertTrue($override->isNew());

    \Drupal::service('event_dispatcher')->removeListener(DefaultLangcodeConfigReconcileEvent::class, $listener);
    $context = $this->reconcile('en', 'fr');
    $this->assertSame('fr', $this->config('config_test.langcode_test')->get('langcode'));
    $this->assertContains('config_test.langcode_test', $context['results']['updated_names'] ?? []);
  }

  /**
   * Tests a subscriber can change the data to save.
   */
  public function testEventChangesData(): void {
    $this->config('config_test.langcode_test')
      ->set('optional_translatable_value', 'Hello')
      ->save();
    \Drupal::service('event_dispatcher')->addListener(DefaultLangcodeConfigReconcileEvent::class, function (DefaultLangcodeConfigReconcileEvent $event): void {
      if ($event->name === 'config_test.langcode_test') {
        $this->assertSame(['optional_translatable_value' => 'Hello'], $event->untranslatedKeys);
        $data = $event->getData();
        $data['optional_translatable_value'] = 'Bonjour';
        $data['langcode'] = 'de';
        $event->setData($data);
      }
    });

    $this->reconcile('en', 'fr');

    $config = $this->config('config_test.langcode_test');
    $this->assertSame('fr', $config->get('langcode'));
    $this->assertSame('Bonjour', $config->get('optional_translatable_value'));
    // The override for the old langcode has the data before the change.
    $override = \Drupal::languageManager()->getLanguageConfigOverride('en', 'config_test.langcode_test');
    $this->assertSame('Hello', $override->get('optional_translatable_value'));
  }

  /**
   * Tests an existing override for the new default is merged and removed.
   */
  public function testExistingOverrideForNewDefaultIsMerged(): void {
    $this->config('config_test.langcode_test')
      ->set('optional_translatable_value', 'Hello')
      ->set('untranslatable_value', 'foo')
      ->save();

    /** @var \Drupal\language\ConfigurableLanguageManagerInterface $language_manager */
    $language_manager = \Drupal::service('language_manager');
    $language_manager->getLanguageConfigOverride('fr', 'config_test.langcode_test')
      ->set('optional_translatable_value', 'Bonjour')
      ->save();

    $this->reconcile('en', 'fr');

    $config = $this->config('config_test.langcode_test');
    $this->assertSame('fr', $config->get('langcode'));
    // The override's value wins over the pre-change active value.
    $this->assertSame('Bonjour', $config->get('optional_translatable_value'));
    // A value the override never touched survives from the active config.
    $this->assertSame('foo', $config->get('untranslatable_value'));

    // The now-redundant override is gone: the active configuration is its
    // own canonical copy from here on.
    $override = $language_manager->getLanguageConfigOverride('fr', 'config_test.langcode_test');
    $this->assertTrue($override->isNew());
  }

  /**
   * Tests an override for an unrelated langcode is left alone.
   */
  public function testOverrideForUnrelatedLangcodeIsUntouched(): void {
    $this->config('config_test.langcode_test')
      ->set('optional_translatable_value', 'Hello')
      ->save();

    /** @var \Drupal\language\ConfigurableLanguageManagerInterface $language_manager */
    $language_manager = \Drupal::service('language_manager');
    $language_manager->getLanguageConfigOverride('de', 'config_test.langcode_test')
      ->set('optional_translatable_value', 'Hallo')
      ->save();

    $this->reconcile('en', 'fr');

    $override = $language_manager->getLanguageConfigOverride('de', 'config_test.langcode_test');
    $this->assertFalse($override->isNew());
    $this->assertSame('Hallo', $override->get('optional_translatable_value'));
  }

  /**
   * Tests the previous default's content is preserved as an override.
   *
   * Otherwise it would simply be lost the moment locale module translates
   * the newly-updated config into the new default - locale has no
   * stored translation "into" English to fall back on, since English is
   * the language this content was authored in, not translated into.
   */
  public function testOldDefaultContentIsPreservedAsOverride(): void {
    $this->config('config_test.langcode_test')
      ->set('optional_translatable_value', 'Hello')
      ->set('untranslatable_value', 'foo')
      ->save();

    $this->reconcile('en', 'fr');

    /** @var \Drupal\language\ConfigurableLanguageManagerInterface $language_manager */
    $language_manager = \Drupal::service('language_manager');
    $override = $language_manager->getLanguageConfigOverride('en', 'config_test.langcode_test');
    $this->assertFalse($override->isNew());
    $this->assertSame('Hello', $override->get('optional_translatable_value'));
    // Only the translatable value is preserved - the override mirrors the
    // shape locale module's own overrides have.
    $this->assertNull($override->get('untranslatable_value'));
  }

  /**
   * Tests moving through several defaults preserves each one along the way.
   *
   * Moving en -> fr -> de must leave both an 'en' override (the original
   * content) and an 'fr' override (whatever was active immediately before
   * the second switch - standing in here for what locale module's own
   * translation would have put there) - not just the first one.
   */
  public function testRoundTripPreservesEachIntermediateLanguage(): void {
    $this->config('config_test.langcode_test')
      ->set('optional_translatable_value', 'Hello')
      ->save();

    $this->reconcile('en', 'fr');

    // Stand in for locale module translating the newly-updated config,
    // the same way it would for a real French translation.
    $this->config('config_test.langcode_test')
      ->set('optional_translatable_value', 'Bonjour')
      ->save();

    $this->reconcile('fr', 'de');

    /** @var \Drupal\language\ConfigurableLanguageManagerInterface $language_manager */
    $language_manager = \Drupal::service('language_manager');

    $english_override = $language_manager->getLanguageConfigOverride('en', 'config_test.langcode_test');
    $this->assertFalse($english_override->isNew());
    $this->assertSame('Hello', $english_override->get('optional_translatable_value'));

    $french_override = $language_manager->getLanguageConfigOverride('fr', 'config_test.langcode_test');
    $this->assertFalse($french_override->isNew());
    $this->assertSame('Bonjour', $french_override->get('optional_translatable_value'));

    $this->assertSame('de', $this->config('config_test.langcode_test')->get('langcode'));
  }

  /**
   * Runs the reconciliation batch operation to completion.
   *
   * @param string $old_langcode
   *   The previous default langcode.
   * @param string $new_langcode
   *   The new default langcode.
   *
   * @return array
   *   The final batch context.
   */
  protected function reconcile(string $old_langcode, string $new_langcode): array {
    $context = [];
    $updater = \Drupal::service(DefaultLangcodeConfigUpdater::class);
    do {
      $updater->reconcileConfigLangcodeOperation($old_langcode, $new_langcode, $context);
    } while ($context['finished'] < 1);
    return $context;
  }

}
