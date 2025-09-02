<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Recipe;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\KernelTests\KernelTestBase;

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

    $recipe = $this->createRecipe($contents);
    RecipeRunner::processRecipe($recipe);

    $display = $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('user', 'user', 'default');
    $component = $display->getComponent('user_picture');
    $this->assertIsArray($component);
    $this->assertSame(true, $component['third_party_settings']['system']['example_flag']);
    $this->assertSame('initial', $component['third_party_settings']['system']['nested']['level_one']['key']);
  }
}


