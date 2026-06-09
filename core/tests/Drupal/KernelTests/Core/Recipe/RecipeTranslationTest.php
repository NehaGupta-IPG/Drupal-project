<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Recipe;

use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests recipe functionality with translations and multilingual sites.
 */
#[CoversClass(RecipeRunner::class)]
#[Group('Recipe')]
#[RunTestsInSeparateProcesses]
class RecipeTranslationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'locale',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'node', 'locale', 'language']);
    $this->installSchema('locale', ['locales_source', 'locales_target', 'locales_location']);
  }

  /**
   * Tests that recipe configuration gets translated to site default language.
   *
   * This test verifies that when a recipe contains configuration in English
   * but is applied to a site with a different default language (Spanish), the
   * configuration is automatically translated to the site's default language.
   * It also tests that existing translations from the locale database are
   * applied to the imported configuration.
   */
  public function testRecipeConfigTranslation(): void {
    // Install Spanish language.
    $language_storage = \Drupal::entityTypeManager()->getStorage('configurable_language');
    $spanish = $language_storage->create(['id' => 'es', 'label' => 'Spanish']);
    $spanish->save();

    // Set Spanish as the default language.
    \Drupal::configFactory()->getEditable('system.site')->set('default_langcode', 'es')->save();
    \Drupal::service('language.default')->set($spanish);

    // Pre-save a Spanish translation for the content type name in the locale database.
    $locale_storage = \Drupal::service('locale.storage');
    $source_string = $locale_storage->createString([
      'source' => 'Recipe Test Content Type',
      'context' => '',
    ])->save();

    $locale_storage->createTranslation([
      'lid' => $source_string->getId(),
      'language' => 'es',
      'translation' => 'Tipo de Contenido de Receta de Prueba',
    ])->save();

    // Add the translation to the configuration location so it gets picked up.
    $source_string->addLocation('configuration', 'node.type.recipe_test')->save();

    // Apply the recipe with English configuration.
    $recipe = Recipe::createFromDirectory('core/tests/fixtures/recipes/recipe_translation_test');
    RecipeRunner::processRecipe($recipe);

    // Verify the configuration was imported.
    $this->assertTrue($this->container->get('config.storage')->exists('node.type.recipe_test'), 'The recipe test content type configuration exists');

    // Verify the imported configuration has Spanish as langcode.
    $config = $this->config('node.type.recipe_test');
    $this->assertSame('es', $config->get('langcode'), 'The imported configuration has Spanish as the language code');

    // Verify that the Spanish translation was applied to the configuration.
    $this->assertSame('Tipo de Contenido de Receta de Prueba', $config->get('name'), 'The Spanish translation was applied to the content type name');

    // Verify that the node type entity exists and is correct.
    $node_type = NodeType::load('recipe_test');
    $this->assertInstanceOf(NodeType::class, $node_type, 'The node type entity was created');
    $this->assertSame('es', $node_type->language()->getId(), 'The node type entity has Spanish as the language');
    $this->assertSame('Tipo de Contenido de Receta de Prueba', $node_type->label(), 'The node type entity has the Spanish translated label');
  }

  /**
   * Tests recipe translation in a multilingual site with multiple languages.
   *
   * This test verifies that when a recipe is applied to a site with Spanish
   * as default but also having Hungarian available, both the Spanish
   * configuration (updated langcode) and Hungarian translation overrides
   * are properly created.
   */
  public function testRecipeMultilingualTranslation(): void {
    $language_storage = \Drupal::entityTypeManager()->getStorage('configurable_language');

    // Install Spanish and Hungarian languages.
    $spanish = $language_storage->create(['id' => 'es', 'label' => 'Spanish']);
    $spanish->save();
    $hungarian = $language_storage->create(['id' => 'hu', 'label' => 'Hungarian']);
    $hungarian->save();

    // Set Spanish as the default language.
    \Drupal::configFactory()->getEditable('system.site')->set('default_langcode', 'es')->save();
    \Drupal::service('language.default')->set($spanish);

    // Pre-save translations for both languages in the locale database.
    $locale_storage = \Drupal::service('locale.storage');
    $source_string = $locale_storage->createString([
      'source' => 'Recipe Test Content Type',
      'context' => '',
    ])->save();

    // Add Spanish translation.
    $locale_storage->createTranslation([
      'lid' => $source_string->getId(),
      'language' => 'es',
      'translation' => 'Tipo de Contenido de Receta de Prueba',
    ])->save();

    // Add Hungarian translation.
    $locale_storage->createTranslation([
      'lid' => $source_string->getId(),
      'language' => 'hu',
      'translation' => 'Recept Teszt Tartalomtípus',
    ])->save();

    // Add the translation to the configuration location so it gets picked up.
    $source_string->addLocation('configuration', 'node.type.recipe_test')->save();

    // Apply the recipe with English configuration.
    $recipe = Recipe::createFromDirectory('core/tests/fixtures/recipes/recipe_translation_test');
    RecipeRunner::processRecipe($recipe);

    // Verify the configuration was imported with Spanish as the langcode.
    $this->assertTrue($this->container->get('config.storage')->exists('node.type.recipe_test'), 'The recipe test content type configuration exists');
    $config = $this->config('node.type.recipe_test');
    $this->assertSame('es', $config->get('langcode'), 'The imported configuration has Spanish as the language code');
    $this->assertSame('Tipo de Contenido de Receta de Prueba', $config->get('name'), 'The Spanish translation was applied to the content type name');

    // Verify that Hungarian translation overrides were created.
    $hungarian_override = \Drupal::service('language_manager')->getLanguageConfigOverride('hu', 'node.type.recipe_test');
    $this->assertFalse($hungarian_override->isNew(), 'Hungarian language override was created');
    $hungarian_data = $hungarian_override->get();
    $this->assertSame('Recept Teszt Tartalomtípus', $hungarian_data['name'], 'Hungarian translation override has the correct translated name');

    // Verify that the node type entity works correctly in Spanish.
    $node_type = NodeType::load('recipe_test');
    $this->assertInstanceOf(NodeType::class, $node_type, 'The node type entity was created');
    $this->assertSame('es', $node_type->language()->getId(), 'The node type entity has Spanish as the language');
    $this->assertSame('Tipo de Contenido de Receta de Prueba', $node_type->label(), 'The node type entity has the Spanish translated label');
  }

  /**
   * Tests recipe translation overrides on English site with additional languages.
   *
   * This test verifies that when a recipe is applied to an English site that
   * also has Spanish available, the imported configuration remains in English
   * but Spanish translation overrides are properly created for the additional
   * language.
   */
  public function testRecipeTranslationOverridesOnEnglishSite(): void {
    $language_storage = \Drupal::entityTypeManager()->getStorage('configurable_language');

    // Install Spanish language (English is already the default).
    $spanish = $language_storage->create(['id' => 'es', 'label' => 'Spanish']);
    $spanish->save();

    // Verify English is still the default language.
    $this->assertSame('en', \Drupal::service('language.default')->get()->getId(), 'English is the default language');

    // Pre-save a Spanish translation for the content type name in the locale database.
    $locale_storage = \Drupal::service('locale.storage');
    $source_string = $locale_storage->createString([
      'source' => 'Recipe Test Content Type',
      'context' => '',
    ])->save();

    $locale_storage->createTranslation([
      'lid' => $source_string->getId(),
      'language' => 'es',
      'translation' => 'Tipo de Contenido de Receta de Prueba',
    ])->save();

    // Add the translation to the configuration location so it gets picked up.
    $source_string->addLocation('configuration', 'node.type.recipe_test')->save();

    // Apply the recipe with English configuration.
    $recipe = Recipe::createFromDirectory('core/tests/fixtures/recipes/recipe_translation_test');
    RecipeRunner::processRecipe($recipe);

    // Verify the configuration was imported and remains in English.
    $this->assertTrue($this->container->get('config.storage')->exists('node.type.recipe_test'), 'The recipe test content type configuration exists');
    $config = $this->config('node.type.recipe_test');
    $this->assertSame('en', $config->get('langcode'), 'The imported configuration keeps English as the language code');
    $this->assertSame('Recipe Test Content Type', $config->get('name'), 'The original English name is preserved in the configuration');

    // Verify that Spanish translation overrides were created.
    $spanish_override = \Drupal::service('language_manager')->getLanguageConfigOverride('es', 'node.type.recipe_test');
    $this->assertFalse($spanish_override->isNew(), 'Spanish language override was created');
    $spanish_data = $spanish_override->get();
    $this->assertSame('Tipo de Contenido de Receta de Prueba', $spanish_data['name'], 'Spanish translation override has the correct translated name');

    // Verify that the node type entity works correctly in English.
    $node_type = NodeType::load('recipe_test');
    $this->assertInstanceOf(NodeType::class, $node_type, 'The node type entity was created');
    $this->assertSame('en', $node_type->language()->getId(), 'The node type entity has English as the language');
    $this->assertSame('Recipe Test Content Type', $node_type->label(), 'The node type entity has the original English label');
  }

}
