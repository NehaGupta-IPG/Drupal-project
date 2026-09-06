<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\DefaultLangcodeConfigUpdater;
use Drupal\language\Entity\ConfigurableLanguage;
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
   * Tests configuration without an explicit langcode gets the new default.
   */
  public function testConfigWithoutExplicitLangcodeIsRelabelled(): void {
    $this->config('config_test.langcode_test')
      ->set('optional_translatable_value', 'Hello')
      ->save();
    $this->assertSame('en', $this->config('config_test.langcode_test')->get('langcode'));

    $this->reconcile('en', 'fr');

    $this->assertSame('fr', $this->config('config_test.langcode_test')->get('langcode'));
  }

  /**
   * Tests configuration explicitly labelled with the previous default.
   */
  public function testConfigExplicitlyLabelledWithOldDefaultIsRelabelled(): void {
    $this->config('config_test.langcode_test')
      ->set('optional_translatable_value', 'Hello')
      ->set('langcode', 'en')
      ->save();

    $this->reconcile('en', 'fr');

    $this->assertSame('fr', $this->config('config_test.langcode_test')->get('langcode'));
  }

  /**
   * Tests configuration explicitly labelled with a third langcode is untouched.
   */
  public function testConfigWithExplicitThirdLangcodeIsUntouched(): void {
    $this->config('config_test.langcode_test')
      ->set('optional_translatable_value', 'Hallo')
      ->set('langcode', 'de')
      ->save();

    $this->reconcile('en', 'fr');

    $this->assertSame('de', $this->config('config_test.langcode_test')->get('langcode'));
    $this->assertSame('Hallo', $this->config('config_test.langcode_test')->get('optional_translatable_value'));
  }

  /**
   * Tests non-translatable configuration loses a stray langcode.
   *
   * Config::save() itself already refuses to keep a langcode on
   * non-translatable configuration, so the only way to get a stray one in
   * place to prove this cleanup is writing it straight to storage - the
   * situation a site upgrading from before this mechanism existed is
   * actually in.
   */
  public function testNonTranslatableConfigLosesStrayLangcode(): void {
    $this->container->get('config.storage')->write('config_test.langcode_test', [
      'langcode' => 'en',
      'untranslatable_value' => 'foo',
    ]);
    $this->assertSame('en', $this->config('config_test.langcode_test')->get('langcode'));

    $this->reconcile('en', 'fr');

    $this->assertNull($this->config('config_test.langcode_test')->get('langcode'));
  }

  /**
   * Tests a config entity without an explicit langcode is relabelled.
   */
  public function testConfigEntityIsRelabelled(): void {
    \Drupal::entityTypeManager()->getStorage('config_test')->create([
      'id' => 'no_explicit_langcode',
      'label' => 'No explicit langcode',
    ])->save();
    $this->assertSame('en', $this->config('config_test.dynamic.no_explicit_langcode')->get('langcode'));

    $this->reconcile('en', 'fr');

    $this->assertSame('fr', $this->config('config_test.dynamic.no_explicit_langcode')->get('langcode'));
  }

  /**
   * Tests the relabelled names are returned for other batch operations.
   */
  public function testRelabelledNamesAreRecorded(): void {
    $this->config('config_test.langcode_test')
      ->set('optional_translatable_value', 'Hello')
      ->set('untranslatable_value', 'foo')
      ->save();

    $context = $this->reconcile('en', 'fr');

    $this->assertContains('config_test.langcode_test', $context['results']['relabelled_names']);
  }

  /**
   * Tests an existing override for the new default is absorbed and removed.
   */
  public function testExistingOverrideForNewDefaultIsAbsorbed(): void {
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
    do {
      DefaultLangcodeConfigUpdater::reconcileConfigLangcodeOperation($old_langcode, $new_langcode, $context);
    } while ($context['finished'] < 1);
    return $context;
  }

}
