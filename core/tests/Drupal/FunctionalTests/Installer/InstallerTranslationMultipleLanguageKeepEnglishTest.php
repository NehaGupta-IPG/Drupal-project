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
   * {@inheritdoc}
   */
  protected function setUpLanguage(): void {
    parent::setUpLanguage();
    // Place an English .po file in the translations directory. If the
    // installer incorrectly attempts to import English translations, these
    // strings will appear in the locales_target table.
    file_put_contents(DRUPAL_ROOT . '/' . $this->siteDirectory . '/files/translations/drupal-' . \Drupal::VERSION . '.en.po', $this->getPo('en'));
  }

  /**
   * Tests that English translations are not fetched with keep_english.
   */
  public function testTranslationsLoaded(): void {
    parent::testTranslationsLoaded();

    // Verify that no English interface translation strings were imported
    // during installation despite an English .po file being available.
    $count = Database::getConnection()->select('locales_target')
      ->condition('language', 'en')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(0, $count, 'No English translations should be imported when translate_english is not enabled.');
  }

}
