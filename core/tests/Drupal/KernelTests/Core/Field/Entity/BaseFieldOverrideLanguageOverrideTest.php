<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Field\Entity;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\entity_test\EntityTestHelper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests language overrides for base field overrides.
 */
#[Group('Field')]
#[RunTestsInSeparateProcesses]
class BaseFieldOverrideLanguageOverrideTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'language',
    'system',
    'user',
  ];

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected ConfigurableLanguageManagerInterface $languageManager;

  /**
   * The ID of the entity type used in this test.
   *
   * @var string
   */
  protected string $testEntityTypeId = 'entity_test';

  /**
   * The bundle used in this test.
   *
   * @var string
   */
  protected string $testBundle = 'test_bundle';

  /**
   * The name of the field used in this test.
   *
   * @var string
   */
  protected string $testFieldName = 'name';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('base_field_override');

    EntityTestHelper::createBundle($this->testBundle);
    ConfigurableLanguage::createFromLangcode('af')->save();

    $this->entityFieldManager = $this->container->get('entity_field.manager');
    $this->languageManager = $this->container->get('language_manager');

    $this->config('language.types')->set('negotiation.language_interface.enabled', [
      'language-selected' => 0,
    ])->save();
    // @todo Remove this in https://www.drupal.org/project/drupal/issues/3544395
    $this->container->get('language_negotiator')->setCurrentUser($this->container->get('current_user'));
  }

  /**
   * Tests a language config override for a base field override.
   */
  public function testLanguageConfigOverride(): void {
    $this->assertEquals('en', $this->languageManager->getCurrentLanguage()->getId());
    $this->assertTestFieldLabel('Name');

    $baseFieldDefinitions = $this->entityFieldManager->getBaseFieldDefinitions($this->testEntityTypeId);
    $this->assertArrayHasKey($this->testFieldName, $baseFieldDefinitions);
    $baseFieldDefinition = $baseFieldDefinitions[$this->testFieldName];
    $this->assertInstanceOf(BaseFieldDefinition::class, $baseFieldDefinition);

    $baseFieldOverride = BaseFieldOverride::createFromBaseFieldDefinition($baseFieldDefinition, $this->testBundle);
    $baseFieldOverride->setLabel('Overridden name in English')->save();
    $this->assertTestFieldLabel('Overridden name in English');

    $this->config('language.negotiation')->set('selected_langcode', 'af')->save();
    $this->languageManager->reset();
    $this->assertEquals('af', $this->languageManager->getCurrentLanguage()->getId());
    $this->entityFieldManager->clearCachedFieldDefinitions();
    $languageConfigOverride = $this->languageManager->getLanguageConfigOverride('af', $baseFieldOverride->getConfigDependencyName());
    $languageConfigOverride->set('label', 'Overridden name in Afrikaans')->save();
    $this->assertTestFieldLabel('Overridden name in Afrikaans');
  }

  /**
   * Tests base field overrides after switching languages without clearing caches.
   */
  public function testBaseFieldOverridesAreCachedPerLanguage(): void {
    $second_bundle = 'second_bundle';
    EntityTestHelper::createBundle($second_bundle);
    $base_field_definition = $this->entityFieldManager
      ->getBaseFieldDefinitions($this->testEntityTypeId)[$this->testFieldName];
    $this->assertInstanceOf(BaseFieldDefinition::class, $base_field_definition);

    // Create all configuration before warming the field definition caches.
    foreach ([$this->testBundle, $second_bundle] as $bundle) {
      $override = BaseFieldOverride::createFromBaseFieldDefinition($base_field_definition, $bundle);
      $override->setLabel("$bundle English")->save();
      $this->languageManager
        ->getLanguageConfigOverride('af', $override->getConfigDependencyName())
        ->set('label', "$bundle Afrikaans")
        ->save();
    }
    $this->entityFieldManager->clearCachedFieldDefinitions();
    $this->assertTestFieldLabel("$this->testBundle English");

    // Rebuild definitions in another language using the same field manager.
    // Reading another bundle must also preserve the first bundle's translation
    // when all bundles are rebuilt together.
    $this->config('language.negotiation')->set('selected_langcode', 'af')->save();
    $this->languageManager->reset();
    $this->assertSame('af', $this->languageManager->getCurrentLanguage()->getId());
    $fields = $this->entityFieldManager->getFieldDefinitions($this->testEntityTypeId, $second_bundle);
    $this->assertSame("$second_bundle Afrikaans", $fields[$this->testFieldName]->getLabel());
    $this->assertTestFieldLabel("$this->testBundle Afrikaans");

    // Switching back must still return the original English definitions.
    $this->config('language.negotiation')->set('selected_langcode', 'en')->save();
    $this->languageManager->reset();
    $this->assertSame('en', $this->languageManager->getCurrentLanguage()->getId());
    $fields = $this->entityFieldManager->getFieldDefinitions($this->testEntityTypeId, $second_bundle);
    $this->assertSame("$second_bundle English", $fields[$this->testFieldName]->getLabel());
    $this->assertTestFieldLabel("$this->testBundle English");
  }

  /**
   * Asserts that the field label of the test field matches the expected value.
   *
   * @param string $expected
   *   The expected field label.
   *
   * @throws \PHPUnit\Framework\ExpectationFailedException
   */
  protected function assertTestFieldLabel(string $expected): void {
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($this->testEntityTypeId, $this->testBundle);
    $this->assertArrayHasKey($this->testFieldName, $fieldDefinitions);
    $fieldDefinition = $fieldDefinitions[$this->testFieldName];
    $this->assertEquals($expected, (string) $fieldDefinition->getLabel());
  }

}
