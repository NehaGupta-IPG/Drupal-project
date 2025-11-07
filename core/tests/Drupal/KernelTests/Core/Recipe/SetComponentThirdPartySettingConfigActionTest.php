<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Recipe;

use Drupal\Core\Config\Action\Plugin\ConfigAction\Deriver\SetComponentThirdPartySettingDeriver;
use Drupal\Core\Config\Action\Plugin\ConfigAction\SetComponentThirdPartySetting;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests the config action to set third-party settings on display components.
 */
#[Group('Recipe')]
#[RunTestsInSeparateProcesses]
#[CoversClass(SetComponentThirdPartySetting::class)]
#[CoversClass(SetComponentThirdPartySettingDeriver::class)]
class SetComponentThirdPartySettingConfigActionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_third_party_test',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig('user');
    $recipe = Recipe::createFromDirectory($this->getDrupalRoot() . '/core/recipes/user_picture');
    RecipeRunner::processRecipe($recipe);
  }

  /**
   * Tests applying third-party settings to a user display component via recipe.
   */
  public function testSetThirdPartySetting(): void {
    /** @var \Drupal\Core\Config\Action\ConfigActionManager $manager */
    $manager = $this->container->get('plugin.manager.config_action');

    $manager->applyAction(
      'setComponentThirdPartySetting',
      'core.entity_view_display.user.user.default',
      [
        'component' => 'user_picture',
        'provider' => 'field_third_party_test',
        'settings' => [
          // Ensure placeholders supported by the action are properly replaced.
          'field_test_field_formatter_third_party_settings_form' => 'one %entity_type',
          'second_field_formatter_third_party_settings_form' => 'two %bundle %display_mode',
        ],
      ],
    );
    $manager->applyAction(
      'setComponentThirdPartySetting',
      'core.entity_form_display.user.user.default',
      [
        'component' => 'user_picture',
        'provider' => 'field_third_party_test',
        'settings' => [
          'field_test_widget_third_party_settings_form' => 'one',
          'second_field_widget_third_party_settings_form' => 'two',
        ],
      ],
    );

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = $this->container->get(EntityDisplayRepositoryInterface::class);

    $component = $display_repository->getViewDisplay('user', 'user')
      ->getComponent('user_picture');
    $this->assertIsArray($component);
    $this->assertSame([
      'field_test_field_formatter_third_party_settings_form' => 'one user',
      'second_field_formatter_third_party_settings_form' => 'two user default',
    ], $component['third_party_settings']['field_third_party_test']);

    $component = $display_repository->getFormDisplay('user', 'user')
      ->getComponent('user_picture');
    $this->assertIsArray($component);
    $this->assertSame([
      'field_test_widget_third_party_settings_form' => 'one',
      'second_field_widget_third_party_settings_form' => 'two',
    ], $component['third_party_settings']['field_third_party_test']);
  }

  /**
   * Tests applying third-party settings to multiple components.
   */
  public function testSetMultipleThirdPartySettings(): void {
    /** @var \Drupal\Core\Config\Action\ConfigActionManager $manager */
    $manager = $this->container->get('plugin.manager.config_action');

    $manager->applyAction(
      'setComponentThirdPartySettings',
      'core.entity_view_display.user.user.default',
      [
        [
          'component' => 'user_picture',
          'provider' => 'field_third_party_test',
          'settings' => [
            'field_test_field_formatter_third_party_settings_form' => 'one',
            'second_field_formatter_third_party_settings_form' => 'two',
          ],
        ],
        [
          'component' => 'member_for',
          'provider' => 'field_third_party_test',
          'settings' => [
            'field_test_field_formatter_third_party_settings_form' => 'three',
            'second_field_formatter_third_party_settings_form' => 'four',
          ],
        ],
      ],
    );

    $display = $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('user', 'user');

    $component = $display->getComponent('user_picture');
    $this->assertIsArray($component);
    $this->assertSame([
      'field_test_field_formatter_third_party_settings_form' => 'one',
      'second_field_formatter_third_party_settings_form' => 'two',
    ], $component['third_party_settings']['field_third_party_test']);

    $component = $display->getComponent('member_for');
    $this->assertIsArray($component);
    $this->assertSame([
      'field_test_field_formatter_third_party_settings_form' => 'three',
      'second_field_formatter_third_party_settings_form' => 'four',
    ], $component['third_party_settings']['field_third_party_test']);
  }

  /**
   * Tests that setting third-party settings on a missing component is an error.
   */
  #[TestWith(['core.entity_view_display.user.user.default'])]
  #[TestWith(['core.entity_form_display.user.user.default'])]
  public function testErrorIfComponentIsMissing(string $config_name): void {
    /** @var \Drupal\Core\Config\Action\ConfigActionManager $manager */
    $manager = $this->container->get('plugin.manager.config_action');

    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage("Component not_a_thing does not exist or hidden.");
    $manager->applyAction(
      'setComponentThirdPartySetting',
      $config_name,
      [
        'component' => 'not_a_thing',
        'provider' => 'nope',
      ],
    );
  }

  /**
   * Ensures that the `component` and `provider` keys are required.
   */
  #[TestWith(['component'])]
  #[TestWith(['provider'])]
  public function testMissingKeysError(string $missing_key): void {
    /** @var \Drupal\Core\Config\Action\ConfigActionManager $manager */
    $manager = $this->container->get('plugin.manager.config_action');

    $value = [
      'component' => 'nope',
      'provider' => 'system',
    ];
    unset($value[$missing_key]);

    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage("The setComponentThirdPartySetting action requires an array with 'component' and 'provider'.");
    $manager->applyAction(
      'setComponentThirdPartySetting',
      'core.entity_view_display.user.user.default',
      $value,
    );
  }

  /**
   * Ensures empty component or provider values are rejected.
   */
  #[TestWith(['component'])]
  #[TestWith(['provider'])]
  public function testEmptyKeysError(string $empty_key): void {
    /** @var \Drupal\Core\Config\Action\ConfigActionManager $manager */
    $manager = $this->container->get('plugin.manager.config_action');

    $value = [
      'component' => 'nope',
      'provider' => 'system',
    ];
    $value[$empty_key] = '';

    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage("The setComponentThirdPartySetting action requires an array with 'component' and 'provider'.");
    $manager->applyAction(
      'setComponentThirdPartySetting',
      'core.entity_form_display.user.user.default',
      $value,
    );
  }

}
