<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\DefaultContent;

use Drupal\Core\DefaultContent\Finder;
use Drupal\Core\DefaultContent\Importer;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests which language default content is imported in.
 *
 * The fixture ships two entities, each with French and Spanish translations,
 * listed in that order. One is written in English, the other in German. German
 * is never a language on the site in these tests, English always is.
 */
#[Group('DefaultContent')]
#[CoversClass(Importer::class)]
#[RunTestsInSeparateProcesses]
class ImporterLanguageTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * The UUID of the fixture entity written in English.
   */
  private const string ENGLISH_UUID = '6d0f9d2e-3a1b-4c5d-8e7f-a1b2c3d4e5f6';

  /**
   * The UUID of the fixture entity written in German.
   */
  private const string GERMAN_UUID = 'b7e1c3a5-9f2d-4e6b-a8c0-d1e2f3a4b5c6';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'entity_test', 'language'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test_mul');
    $this->installConfig(['system', 'language']);
    $this->setUpCurrentUser(admin: TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    unset($GLOBALS['install_state']);
    parent::tearDown();
  }

  /**
   * Data provider for ::testImportLanguage().
   *
   * Each case lists whether Drupal is being installed, the site's default
   * language, the additional languages on the site, and the expected language,
   * label and translations of the English and the German fixture entities.
   *
   * @return \Generator<string, array{bool, string, list<string>, array<string, array{string, string, array<string, string>}>}>
   *   The test cases.
   */
  public static function providerImportLanguage(): \Generator {
    $french = 'Traduit en français';
    $spanish = 'Traducido al español';
    $english = 'Written in English';

    // The content's language is a language on the site and Drupal is not being
    // installed: the content is imported as-is, with all its translations.
    yield 'Spanish site, not installing' => [
      FALSE, 'es', ['es', 'fr'],
      [
        // The English entity is left alone.
        self::ENGLISH_UUID => ['en', $english, ['es' => $spanish, 'fr' => $french]],
        // German is unknown, so the Spanish translation becomes the default:
        // it is preferred over French, which is listed first. The German
        // values cannot be kept, because the site does not know German.
        self::GERMAN_UUID => ['es', $spanish, ['fr' => $french]],
      ],
    ];

    // During installation, content is imported in the site's default language
    // whenever a translation exists in it. The original values are kept as a
    // translation, because the site knows English.
    yield 'Spanish site, installing' => [
      TRUE, 'es', ['es', 'fr'],
      [
        self::ENGLISH_UUID => ['es', $spanish, ['en' => $english, 'fr' => $french]],
        self::GERMAN_UUID => ['es', $spanish, ['fr' => $french]],
      ],
    ];

    // No translation exists in the site's default language, so the first
    // translation in a language the site knows is used instead.
    yield 'Hungarian site, installing' => [
      TRUE, 'hu', ['hu', 'es', 'fr'],
      [
        self::ENGLISH_UUID => ['fr', $french, ['en' => $english, 'es' => $spanish]],
        self::GERMAN_UUID => ['fr', $french, ['es' => $spanish]],
      ],
    ];

    // No usable translation exists at all, so the content is imported in the
    // site's default language, with its values unchanged and no translations.
    yield 'Hungarian site without other languages, installing' => [
      TRUE, 'hu', ['hu'],
      [
        self::ENGLISH_UUID => ['hu', $english, []],
        self::GERMAN_UUID => ['hu', 'Auf Deutsch geschrieben', []],
      ],
    ];
  }

  /**
   * Tests the language in which default content with translations is imported.
   *
   * @param bool $installing
   *   Whether to import as if Drupal were being installed.
   * @param string $default_langcode
   *   The site's default language.
   * @param list<string> $langcodes
   *   The languages to add to the site, on top of English.
   * @param array<string, array{string, string, array<string, string>}> $expected
   *   For each fixture entity UUID: the expected language of the entity, its
   *   expected label, and its expected translations as langcode => label.
   */
  #[DataProvider('providerImportLanguage')]
  public function testImportLanguage(bool $installing, string $default_langcode, array $langcodes, array $expected): void {
    foreach ($langcodes as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }
    $this->config('system.site')->set('default_langcode', $default_langcode)->save();
    $this->container->get('language_manager')->reset();

    // The importer treats content differently while Drupal is being installed.
    // @see \Drupal\Core\Installer\InstallerKernel::installationAttempted()
    if ($installing) {
      $GLOBALS['install_state'] = ['installation_finished' => FALSE];
    }
    $finder = new Finder($this->root . '/core/tests/fixtures/default_content_translations');
    $this->container->get(Importer::class)->importContent($finder);
    unset($GLOBALS['install_state']);

    $repository = $this->container->get(EntityRepositoryInterface::class);
    foreach ($expected as $uuid => [$langcode, $label, $translations]) {
      $entity = $repository->loadEntityByUuid('entity_test_mul', $uuid);
      $this->assertInstanceOf(ContentEntityInterface::class, $entity);
      $this->assertSame($langcode, $entity->language()->getId(), "Entity $uuid has the expected language.");
      $this->assertSame($label, $entity->label(), "Entity $uuid has the expected label.");

      $actual_translations = [];
      foreach (array_keys($entity->getTranslationLanguages(FALSE)) as $translation_langcode) {
        $actual_translations[$translation_langcode] = $entity->getTranslation($translation_langcode)->label();
      }
      ksort($actual_translations);
      ksort($translations);
      $this->assertSame($translations, $actual_translations, "Entity $uuid has the expected translations.");
    }
  }

}
