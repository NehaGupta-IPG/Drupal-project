<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use Drupal\Core\Database\Database;
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

    // Verify that English is a configured language but not translatable.
    $languages = \Drupal::languageManager()->getLanguages();
    $this->assertArrayHasKey('en', $languages);
    $translatable_languages = locale_translatable_language_list();
    $this->assertArrayNotHasKey('en', $translatable_languages);

    // The locale_test_translate module provides a local translation file
    // pattern (test.%language.po). A test.en.po file exists with a distinctive
    // "Monday EN" translation. Verify it was NOT imported during installation,
    // proving that English was excluded from the translation fetch phase.
    $count = Database::getConnection()->select('locales_target')
      ->condition('language', 'en')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(0, $count, 'No English translations should be imported when translate_english is not enabled.');
  }

}
