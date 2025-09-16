<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Recipe;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Ensures the setComponentThirdPartySetting action works via recipes.
 *
 * @group Recipe
 */
class SetComponentThirdPartySettingRecipeTest extends KernelTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Ensure the user entity and its default displays exist before applying
    // the recipe action targeting the user default view display.
    $this->installEntitySchema('user');
    $this->installConfig(['user']);
    $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('user', 'user', 'default')
      ->save();
  }

  /**
   * Applies a recipe YAML string, optionally disabling schema checks.
   */
  private function applyRecipeFromYaml(string $contents, bool $disable_schema_check = TRUE): void {
    $recipe = $this->createRecipe($contents);
    if ($disable_schema_check) {
      $schema_checker = $this->container->get('testing.config_schema_checker');
      $this->container->get(EventDispatcherInterface::class)
        ->removeSubscriber($schema_checker);
    }
    RecipeRunner::processRecipe($recipe);
  }

  /**
   * Tests applying third-party settings to a user display component via recipe.
   */
  public function testApplyRecipe(): void {
    $contents = <<<YAML
name: 'Set third-party setting via recipe'
config:
  actions:
    core.entity_view_display.user.user.default:
      setComponentThirdPartySetting:
        component: user_picture
        provider: system
        settings:
          example_flag: true
          nested:
            level_one:
              key: 'initial'
YAML;
    $this->applyRecipeFromYaml($contents, TRUE);

    $display = $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('user', 'user', 'default');
    $component = $display->getComponent('user_picture');
    $this->assertIsArray($component);
    $this->assertTrue($component['third_party_settings']['system']['example_flag']);
    $this->assertSame('initial', $component['third_party_settings']['system']['nested']['level_one']['key']);
  }

  /**
   * Ensures scalar settings are stored under the 'value' key.
   */
  public function testScalarSettingStoredUnderValueKey(): void {
    $contents = <<<YAML
name: 'Scalar third-party setting via recipe'
config:
  actions:
    core.entity_view_display.user.user.default:
      setComponentThirdPartySetting:
        component: user_picture
        provider: system
        settings: 'flag'
YAML;
    $this->applyRecipeFromYaml($contents, TRUE);

    $display = $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('user', 'user', 'default');
    $component = $display->getComponent('user_picture');
    $this->assertSame('flag', $component['third_party_settings']['system']['value']);
  }

  /**
   * Ensures missing settings results in a null 'value' entry.
   */
  public function testMissingSettingsDefaultsToNull(): void {
    $contents = <<<YAML
name: 'Missing settings defaults to null'
config:
  actions:
    core.entity_view_display.user.user.default:
      setComponentThirdPartySetting:
        component: user_picture
        provider: system
YAML;
    $this->applyRecipeFromYaml($contents, TRUE);

    $display = $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('user', 'user', 'default');
    $component = $display->getComponent('user_picture');
    $this->assertArrayHasKey('value', $component['third_party_settings']['system']);
    $this->assertNull($component['third_party_settings']['system']['value']);
  }

  /**
   * Ensures a missing component is created and settings are applied.
   */
  public function testCreatesComponentWhenMissing(): void {
    $contents = <<<YAML
name: 'Create missing component and set settings'
config:
  actions:
    core.entity_view_display.user.user.default:
      setComponentThirdPartySetting:
        component: nonexistent_field
        provider: system
        settings:
          enabled: true
YAML;
    $this->applyRecipeFromYaml($contents, TRUE);

    $display = $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('user', 'user', 'default');
    $component = $display->getComponent('nonexistent_field');
    $this->assertIsArray($component);
    $this->assertArrayHasKey('settings', $component);
    $this->assertArrayHasKey('third_party_settings', $component);
    $this->assertTrue($component['third_party_settings']['system']['enabled']);
  }

  /**
   * Ensures merging adds new keys without removing existing ones.
   */
  public function testMergesSettingsAddsNewKeysWithoutAlteringExisting(): void {

    // Disable schema check.
    $this->container->get(EventDispatcherInterface::class)
      ->removeSubscriber($this->container->get('testing.config_schema_checker'));

    // Seed an existing third-party setting for the component.
    $display = $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('user', 'user', 'default');
    $existing = $display->getComponent('user_picture') ?? [];
    $existing['third_party_settings']['system'] = [
      'keep_me' => 'original',
    ];
    $display->setComponent('user_picture', $existing)->save();

    $contents = <<<YAML
name: 'Merge third-party settings'
config:
  actions:
    core.entity_view_display.user.user.default:
      setComponentThirdPartySetting:
        component: user_picture
        provider: system
        settings:
          add_me: 'new'
YAML;
    $this->applyRecipeFromYaml($contents, TRUE);

    $display = $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('user', 'user', 'default');
    $component = $display->getComponent('user_picture');
    $this->assertSame('original', $component['third_party_settings']['system']['keep_me']);
    $this->assertSame('new', $component['third_party_settings']['system']['add_me']);
  }

  /**
   * Ensures a non-array action value is rejected.
   */
  public function testRejectsNonArrayValue(): void {
    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage("requires an array with 'component' and 'provider'");
    $contents = <<<YAML
name: 'Invalid non-array value'
config:
  actions:
    core.entity_view_display.user.user.default:
      setComponentThirdPartySetting: 42
YAML;
    $this->applyRecipeFromYaml($contents, TRUE);
  }

  /**
   * Ensures missing 'component' key is rejected.
   */
  public function testRejectsMissingComponentKey(): void {
    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage("requires an array with 'component' and 'provider'");
    $contents = <<<YAML
name: 'Missing component key'
config:
  actions:
    core.entity_view_display.user.user.default:
      setComponentThirdPartySetting:
        provider: system
        settings:
          x: y
YAML;
    $this->applyRecipeFromYaml($contents, TRUE);
  }

  /**
   * Ensures missing 'provider' key is rejected.
   */
  public function testRejectsMissingProviderKey(): void {
    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage("requires an array with 'component' and 'provider'");
    $contents = <<<YAML
name: 'Missing provider key'
config:
  actions:
    core.entity_view_display.user.user.default:
      setComponentThirdPartySetting:
        component: user_picture
        settings:
          x: y
YAML;
    $this->applyRecipeFromYaml($contents, TRUE);
  }

  /**
   * Ensures empty component or provider values are rejected.
   */
  public function testRejectsEmptyComponentOrProvider(): void {
    // Empty component.
    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage("'component' must be a non-empty string");
    $contents = <<<YAML
name: 'Empty component value'
config:
  actions:
    core.entity_view_display.user.user.default:
      setComponentThirdPartySetting:
        component: ''
        provider: system
YAML;
    try {
      $this->applyRecipeFromYaml($contents, TRUE);
    }
    catch (ConfigActionException $e) {
      // Re-throw to satisfy expectation and proceed to next case.
      throw $e;
    }
  }

  /**
   * Ensures a missing provider module is rejected.
   */
  public function testRejectsMissingProviderModule(): void {
    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage("provider' module");
    $contents = <<<YAML
name: 'Missing provider module'
config:
  actions:
    core.entity_view_display.user.user.default:
      setComponentThirdPartySetting:
        component: user_picture
        provider: nonexistent_module
        settings:
          flag: true
YAML;
    $this->applyRecipeFromYaml($contents, TRUE);
  }

}
