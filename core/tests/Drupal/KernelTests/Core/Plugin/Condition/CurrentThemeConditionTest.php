<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Plugin\Condition;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the CurrentThemeCondition plugin.
 */
#[Group('Condition')]
#[RunTestsInSeparateProcesses]
class CurrentThemeConditionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'theme_test'];

  /**
   * Tests the current theme condition.
   */
  public function testCurrentTheme(): void {
    \Drupal::service('theme_installer')->install(['starterkit_theme', 'test_base_theme', 'test_subtheme']);

    $manager = \Drupal::service('plugin.manager.condition');
    /** @var \Drupal\Core\Condition\ConditionInterface $condition */
    $condition = $manager->createInstance('current_theme');
    $condition->setConfiguration(['themes' => ['starterkit_theme']]);
    /** @var \Drupal\Core\Condition\ConditionInterface $condition_negated */
    $condition_negated = $manager->createInstance('current_theme');
    $condition_negated->setConfiguration(['themes' => ['starterkit_theme'], 'negate' => TRUE]);
    /** @var \Drupal\Core\Condition\ConditionInterface $condition_multiple */
    $condition_multiple = $manager->createInstance('current_theme');
    $condition_multiple->setConfiguration(['themes' => ['test_subtheme', 'starterkit_theme']]);
    /** @var \Drupal\Core\Condition\ConditionInterface $condition_multiple_negated */
    $condition_multiple_negated = $manager->createInstance('current_theme');
    $condition_multiple_negated->setConfiguration(['themes' => ['test_subtheme', 'starterkit_theme'], 'negate' => TRUE]);
    /** @var \Drupal\Core\Condition\ConditionInterface $condition_any */
    $condition_any = $manager->createInstance('current_theme');
    $condition_any->setConfiguration(['themes' => []]);
    /** @var \Drupal\Core\Condition\ConditionInterface $condition_any_negated */
    $condition_any_negated = $manager->createInstance('current_theme');
    $condition_any_negated->setConfiguration(['themes' => [], 'negate' => TRUE]);

    // The expected theme has not been set up yet.
    $this->assertFalse($condition->execute());
    $this->assertTrue($condition_negated->execute());

    // Set the expected theme to be used.
    $this->config('system.theme')->set('default', 'starterkit_theme')->save();
    \Drupal::theme()->resetActiveTheme();

    $this->assertEquals('The current theme is: Starterkit theme', $condition->summary());
    $this->assertEquals('The current theme is not: Starterkit theme', $condition_negated->summary());
    $this->assertEquals('The current theme is one of the following: Starterkit theme, Theme test subtheme', $condition_multiple->summary());
    $this->assertEquals('The current theme is not one of the following: Starterkit theme, Theme test subtheme', $condition_multiple_negated->summary());
    $this->assertEquals('The current theme is one of the following: Starterkit theme, Theme test base theme, Theme test subtheme', $condition_any->summary());
    $this->assertEquals('The current theme is not one of the following: Starterkit theme, Theme test base theme, Theme test subtheme', $condition_any_negated->summary());

    $this->assertTrue($condition->execute());
    $this->assertFalse($condition_negated->execute());

    // The multiple condition should also apply:
    $this->assertTrue($condition_multiple->execute());
    $this->assertFalse($condition_multiple_negated->execute());

    // Also when setting the other expected theme:
    $this->config('system.theme')->set('default', 'test_subtheme')->save();
    \Drupal::theme()->resetActiveTheme();

    $this->assertTrue($condition_multiple->execute());
    $this->assertFalse($condition_multiple_negated->execute());

    // If we set another theme, the condition should not apply:
    $this->config('system.theme')->set('default', 'test_base_theme')->save();
    \Drupal::theme()->resetActiveTheme();

    $this->assertFalse($condition_multiple->execute());
    $this->assertTrue($condition_multiple_negated->execute());

    // The any condition should always apply no matter the theme:
    $this->assertTrue($condition_any->execute());
    $this->assertFalse($condition_any_negated->execute());

    $this->config('system.theme')->set('default', 'starterkit_theme')->save();
    \Drupal::theme()->resetActiveTheme();

    $this->assertTrue($condition_any->execute());
    $this->assertFalse($condition_any_negated->execute());

    $this->config('system.theme')->set('default', 'test_subtheme')->save();
    \Drupal::theme()->resetActiveTheme();

    $this->assertTrue($condition_any->execute());
    $this->assertFalse($condition_any_negated->execute());
  }

  /**
   * Tests the old deprecated current theme definition.
   *
   * Tests whether the old current theme condition 'theme' configuration key
   * still works as expected and throws the defined deprecation message.
   */
  #[IgnoreDeprecations]
  public function testCurrentThemeDeprecated(): void {
    \Drupal::service('theme_installer')->install(['starterkit_theme', 'test_base_theme']);

    $manager = \Drupal::service('plugin.manager.condition');

    // Check whether the deprecated config works as expected
    /** @var \Drupal\Core\Condition\ConditionInterface $condition_deprecated_config */
    $condition_deprecated_config = $manager->createInstance('current_theme');
    $condition_deprecated_config->setConfiguration(['theme' => 'starterkit_theme']);

    $this->expectUserDeprecationMessage('The "theme" configuration key is deprecated in drupal:12.0.0 and will be removed in drupal:13.0.0. Use "themes" instead. See https://www.drupal.org/node/3523553');

    // Check whether the deprecated config still functions as expected:
    $this->assertFalse($condition_deprecated_config->execute());

    // Set the expected theme to be used.
    $this->config('system.theme')->set('default', 'starterkit_theme')->save();
    \Drupal::theme()->resetActiveTheme();

    $this->assertEquals('The current theme is: Starterkit theme', $condition_deprecated_config->summary());

    // Check whether the deprecated config still functions as expected:
    $this->assertTrue($condition_deprecated_config->execute());
  }

}
