<?php

declare(strict_types=1);

namespace Drupal\Tests\config_translation\Functional;

use Drupal\entity_test\EntityTestHelper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests translating the default value URI of link and uri fields.
 *
 * @see https://www.drupal.org/project/drupal/issues/3595275
 */
#[Group('config_translation')]
#[RunTestsInSeparateProcesses]
class ConfigTranslationFieldDefaultValueTest extends ConfigTranslationUiTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['link'];

  /**
   * Tests that the default value URI of a link field is translatable.
   */
  public function testLinkFieldDefaultValueTranslation(): void {
    $this->assertFieldDefaultValueUriIsTranslatable('link', 'uri', ['uri' => 'https://example.com', 'title' => 'Example']);
  }

  /**
   * Tests that the default value of a uri field is translatable.
   */
  public function testUriFieldDefaultValueTranslation(): void {
    $this->assertFieldDefaultValueUriIsTranslatable('uri', 'value', ['value' => 'https://example.com']);
  }

  /**
   * Asserts that a field's default value URI can be translated through the UI.
   *
   * @param string $field_type
   *   The field type to create.
   * @param string $value_key
   *   The key in the default value that holds the URI.
   * @param array $default_value
   *   The default value to set on the field.
   */
  protected function assertFieldDefaultValueUriIsTranslatable(string $field_type, string $value_key, array $default_value): void {
    $field_name = $this->randomMachineName();
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => $field_type,
    ])->save();

    $bundle = $this->randomMachineName();
    EntityTestHelper::createBundle($bundle);
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'bundle' => $bundle,
      'default_value' => [$default_value],
    ])->save();

    $config_name = "field.field.entity_test.$bundle.$field_name";
    $element_name = "translation[config_names][$config_name][default_value][0][$value_key]";

    $this->drupalLogin($this->translatorUser);
    $this->drupalGet("/entity_test/structure/$bundle/fields/entity_test.$bundle.$field_name/translate/fr/add");

    // The URI default value is exposed as a translatable form element with the
    // source value pre-filled.
    $this->assertSession()->fieldValueEquals($element_name, 'https://example.com');

    // Save a French translation of the default value URI.
    $this->submitForm([$element_name => 'https://example.com/fr'], 'Save translation');
    $this->assertSession()->pageTextContains('Successfully saved French translation.');

    // The translation is persisted and shown when editing again.
    $this->drupalGet("/entity_test/structure/$bundle/fields/entity_test.$bundle.$field_name/translate/fr/edit");
    $this->assertSession()->fieldValueEquals($element_name, 'https://example.com/fr');

    // The language override stores the translated URI.
    $override = \Drupal::languageManager()->getLanguageConfigOverride('fr', $config_name);
    $this->assertSame('https://example.com/fr', $override->get("default_value.0.$value_key"));
  }

}
