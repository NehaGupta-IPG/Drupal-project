<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel;

use Drupal\entity_test\Entity\EntityTestMulRevPub;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\FileReferenceResolver;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests file references and access when the referencing entity is translated.
 */
#[Group('file')]
#[RunTestsInSeparateProcesses]
class FileReferenceResolverTest extends FileManagedUnitTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'language'];

  /**
   * A user who may view published entities and their translations.
   */
  protected UserInterface $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_mulrevpub');
    $this->user = $this->createUser([
      'view test entity',
      'view test entity translations',
    ]);
    ConfigurableLanguage::createFromLangcode('fr')->save();

    FieldStorageConfig::create([
      'type' => 'file',
      'entity_type' => 'entity_test_mulrevpub',
      'field_name' => 'field_file',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_mulrevpub',
      'bundle' => 'entity_test_mulrevpub',
      'field_name' => 'field_file',
      'label' => 'File',
      'translatable' => TRUE,
    ])->save();

    FieldStorageConfig::create([
      'type' => 'file',
      'entity_type' => 'entity_test_mulrevpub',
      'field_name' => 'field_shared',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_mulrevpub',
      'bundle' => 'entity_test_mulrevpub',
      'field_name' => 'field_shared',
      'label' => 'Shared file',
      'translatable' => FALSE,
    ])->save();
  }

  /**
   * Tests a file held only by a translation other than the default one.
   */
  public function testFileOnNonDefaultTranslation(): void {
    $file = $this->createPrivateFile();
    $entity = EntityTestMulRevPub::create(['name' => 'Test', 'langcode' => 'en']);
    $entity->setUnpublished()->save();
    $entity->addTranslation('fr', ['name' => 'Test FR'])
      ->setPublished()
      ->set('field_file', $file)
      ->save();

    $this->assertSame(['fr'], $this->getReferencingLangcodes($file));
    $this->assertTrue($file->access('download', $this->user));
  }

  /**
   * Tests a file held by an untranslatable field, shared by all translations.
   */
  public function testFileOnUntranslatableField(): void {
    $file = $this->createPrivateFile();
    $entity = EntityTestMulRevPub::create(['name' => 'Test', 'langcode' => 'en']);
    $entity->setUnpublished()->save();
    $entity->set('field_shared', $file)->save();
    $entity->addTranslation('fr', ['name' => 'Test FR'])->setPublished()->save();

    $this->assertSame(['en', 'fr'], $this->getReferencingLangcodes($file));
    $this->assertTrue($file->access('download', $this->user));
  }

  /**
   * Tests a file that several translations reference at once.
   */
  public function testFileOnSeveralTranslations(): void {
    $file = $this->createPrivateFile();
    $entity = EntityTestMulRevPub::create(['name' => 'Test', 'langcode' => 'en']);
    $entity->setPublished()->save();
    $entity->set('field_file', $file)->save();
    $entity->addTranslation('fr', ['name' => 'Test FR'])
      ->setUnpublished()
      ->set('field_file', $file)
      ->save();

    $this->assertSame(['en', 'fr'], $this->getReferencingLangcodes($file));
    $this->assertTrue($file->access('download', $this->user));
  }

  /**
   * Tests that a file stays protected when no translation is viewable.
   */
  public function testFileWithoutViewableTranslation(): void {
    $file = $this->createPrivateFile();
    $entity = EntityTestMulRevPub::create(['name' => 'Test', 'langcode' => 'en']);
    $entity->setUnpublished()->save();
    $entity->addTranslation('fr', ['name' => 'Test FR'])
      ->setUnpublished()
      ->set('field_file', $file)
      ->save();

    $this->assertSame(['fr'], $this->getReferencingLangcodes($file));
    $this->assertFalse($file->access('download', $this->user));
  }

  /**
   * Tests a file held by a translation in a non-default revision.
   */
  public function testFileOnNonDefaultRevisionTranslation(): void {
    $file = $this->createPrivateFile();
    $entity = EntityTestMulRevPub::create(['name' => 'Test', 'langcode' => 'en']);
    $entity->setPublished()->save();
    $entity->addTranslation('fr', ['name' => 'view revision'])
      ->setPublished()
      ->set('field_file', $file)
      ->save();

    // Replace the file in a new revision, leaving the original reachable only
    // from the old one.
    $entity = EntityTestMulRevPub::load($entity->id());
    $entity->getTranslation('fr')->set('field_file', $this->createPrivateFile());
    $entity->setNewRevision(TRUE);
    $entity->save();

    $usages = $this->getUsages($file);
    $this->assertCount(1, $usages);
    $this->assertNotNull($usages[0]->revisionId);
    $this->assertSame(['fr'], $this->getReferencingLangcodes($file));
    $this->assertTrue($file->access('download', $this->user));
  }

  /**
   * Tests a file held only by a translation the user cannot view.
   */
  public function testFileOnlyOnUnpublishedTranslation(): void {
    $file = $this->createPrivateFile();
    $entity = EntityTestMulRevPub::create(['name' => 'Test', 'langcode' => 'en']);
    $entity->setPublished()->save();
    $entity->addTranslation('fr', ['name' => 'Test FR'])
      ->setUnpublished()
      ->set('field_file', $file)
      ->save();

    $this->assertSame(['fr'], $this->getReferencingLangcodes($file));
    $this->assertFalse($file->access('download', $this->user));
  }

  /**
   * Creates a saved file entity in the private stream.
   *
   * @return \Drupal\file\FileInterface
   *   The saved file.
   */
  protected function createPrivateFile(): FileInterface {
    $file = File::create([
      'uri' => 'private://' . $this->randomMachineName() . '.txt',
      'status' => 1,
    ]);
    $file->save();
    return $file;
  }

  /**
   * Returns the languages of the translations referencing a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file to resolve references for.
   *
   * @return string[]
   *   The sorted langcodes.
   */
  protected function getReferencingLangcodes(FileInterface $file): array {
    $langcodes = [];
    foreach ($this->getUsages($file) as $usage) {
      $langcodes[] = $usage->langcode;
    }
    sort($langcodes);
    return $langcodes;
  }

  /**
   * Returns the usages reported for a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file to resolve references for.
   *
   * @return \Drupal\file\FileReferenceUsage[]
   *   The reported usages.
   */
  protected function getUsages(FileInterface $file): array {
    return iterator_to_array(\Drupal::service(FileReferenceResolver::class)->getReferences($file));
  }

}
