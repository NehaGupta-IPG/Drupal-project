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

    // Verify that English is not included in the translatable language list
    // even though keep_english added it as a configured language.
    $translatable_languages = locale_translatable_language_list();
    $this->assertArrayNotHasKey('en', $translatable_languages);

    // Verify that no English translation was fetched during installation by
    // checking the translation status does not include English entries.
    $status = \Drupal::keyValue('locale.translation_status')->getAll();
    foreach ($status as $project => $project_status) {
      if (is_array($project_status)) {
        $this->assertArrayNotHasKey('en', $project_status, "Project '$project' should not have English translation status.");
      }
    }
  }

}
