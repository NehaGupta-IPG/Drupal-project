<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that keeping English in a foreign language install works.
 */
#[Group('Installer')]
#[Group('#slow')]
#[RunTestsInSeparateProcesses]
class InstallerTranslationMultipleLanguageKeepEnglishTest extends InstallerTranslationMultipleLanguageForeignTest {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Switch to the multilingual testing profile with English kept.
   *
   * @var string
   */
  protected $profile = 'testing_multilingual_with_english';

  /**
   * Tests that English translations are not fetched with keep_english.
   */
  public function testTranslationsLoaded(): void {
    parent::testTranslationsLoaded();

    // With keep_english, English is a configured language on the site.
    $languages = \Drupal::languageManager()->getLanguages();
    $this->assertArrayHasKey('en', $languages);

    // But English should not be in the translatable language list since
    // translate_english is not enabled.
    $translatable_languages = locale_translatable_language_list();
    $this->assertArrayNotHasKey('en', $translatable_languages);

    // The locale_test_fetch_langcodes module decorates LocaleFetch and records
    // the langcodes passed to buildUpdateBatch() during installation. Verify
    // that English was not included.
    $langcodes = \Drupal::state()->get('locale_test_fetch_langcodes.langcodes');
    $this->assertIsArray($langcodes, 'The fetch langcodes should have been recorded during installation.');
    $this->assertNotContains('en', $langcodes, 'English should not be passed to buildUpdateBatch when translate_english is not enabled.');
    $this->assertContains('de', $langcodes);
    $this->assertContains('es', $langcodes);
  }

}
