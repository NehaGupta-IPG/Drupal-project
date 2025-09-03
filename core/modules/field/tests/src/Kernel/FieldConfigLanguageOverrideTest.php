<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\entity_test\EntityTestHelper;
use Drupal\field\Entity\FieldConfig;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests language overrides for config fields.
 */
#[Group('field')]
class FieldConfigLanguageOverrideTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
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
   * The bundle used in this test.
   *
   * @var string
   */
  protected string $testBundle = 'test_bundle';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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
    $this->createFieldWithStorage(bundle: $this->testBundle);
    $fieldConfig = $this->fieldTestData->field;
    $this->assertInstanceOf(FieldConfig::class, $fieldConfig);
    $label = $fieldConfig->getLabel();
    $this->assertTestFieldLabel($label);

    $this->config('language.negotiation')->set('selected_langcode', 'af')->save();
    $this->languageManager->reset();
    $this->assertEquals('af', $this->languageManager->getCurrentLanguage()->getId());
    $languageConfigOverride = $this->languageManager->getLanguageConfigOverride('af', $fieldConfig->getConfigDependencyName());
    $languageConfigOverride->set('label', "$label (Afrikaans override)")->save();
    $this->assertTestFieldLabel("$label (Afrikaans override)");
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
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('entity_test', $this->testBundle);
    $this->assertArrayHasKey($this->fieldTestData->field_name, $fieldDefinitions);
    $fieldDefinition = $fieldDefinitions[$this->fieldTestData->field_name];
    $this->assertEquals($expected, (string) $fieldDefinition->getLabel());
  }

}
