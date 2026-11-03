<?php

namespace Drupal\Tests\locale\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\locale\Gettext;
use org\bovigo\vfs\vfsStream;

// cSpell:ignore Bokmål Skjema Hjem Brukergrensesnitt Tittel Brødtekst Neste Små
// cSpell:ignore grønne menn Verten

/**
 * Tests that the batch importer works correctly.
 *
 * @group locale
 */
class LocaleBatchImportTest extends KernelTestBase {

  /**
   * Path to the translation file used for testing.
   *
   * @var string
   */
  protected $filePath;

  /**
   * Content of the localization file.
   *
   * The last few empty lines at the end of the string are important, they are
   * used to trigger an edge case: if PoStreamReader reaches the end of the
   * parsed file, PoStreamReader::getSeekLastItem() has to return the actual
   * pointer position.
   *
   * @see \Drupal\Component\Gettext\PoStreamReader::getSeekLastItem
   *
   * @const string
   */
  const PO_CONTENT = <<<'EOS'
msgid ""
msgstr ""
"Project-Id-Version: Drupal core (8.x.x)\n"
"POT-Creation-Date: 2020-04-10 20:43+0000\n"
"PO-Revision-Date: YYYY-mm-DD HH:MM+ZZZZ\n"
"Language-Team: Norwegian Bokmål\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=utf-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Plural-Forms: nplurals=2; plural=(n!=1);\n"

msgid "Forms"
msgstr "Skjema"
msgid "Home"
msgstr "Hjem"
msgid "User interface"
msgstr "Brukergrensesnitt"
msgid "Title"
msgstr "Tittel"
msgid "Body"
msgstr "Brødtekst"
msgid "Next"
msgstr "Neste"
msgid "Little green men"
msgstr "Små grønne menn"
msgid "The host"
msgstr "Verten"



EOS;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'locale',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    vfsStream::setup('translations');
    $translations_directory = vfsStream::url('translations');
    // Copy test po files to the translations directory.
    $this->filePath = \Drupal::service('file_system')
      ->saveData(self::PO_CONTENT, $translations_directory . '/test.nb.po');
    $this->installSchema('locale', [
      'locales_target',
      'locales_source',
    ]);
  }

  /**
   * Tests that importing batches imports all translations.
   *
   * The test po file has 8 translations and we import it
   * with chunk size 5, so two batches are needed.
   *
   * To mimic locale_translate_batch_import we call
   * Gettext::fileToDatabase(). This will verify if
   * file seeking behaves correctly.
   *
   * @see https://www.drupal.org/project/drupal/issues/3126929
   */
  public function testImportBatches(): void {
    $file = (object) [
      'langcode' => 'nb',
      'uri' => $this->filePath,
    ];
    $options = [
      'items' => 5,
    ];
    for ($i = 0; $i < 2; $i++) {
      $report = Gettext::fileToDatabase($file, $options);
      $options['seek'] = $report['seek'];
    }
    // Make sure all the translations got imported.
    $translated_strings = \Drupal::database()->select('locales_target', 'lt')
      ->fields('lt', ['lid', 'translation'])
      ->orderBy('lt.lid')
      ->execute()
      ->fetchAllKeyed();
    $this->assertEquals(
      [
        1 => 'Skjema',
        2 => 'Hjem',
        3 => 'Brukergrensesnitt',
        4 => 'Tittel',
        5 => 'Brødtekst',
        6 => 'Neste',
        7 => 'Små grønne menn',
        8 => 'Verten',
      ],
      $translated_strings
    );
    // Verify that locale_translate_batch_import batch can finish.
    $this->assertSame($options['seek'], filesize($this->filePath));
  }

}
