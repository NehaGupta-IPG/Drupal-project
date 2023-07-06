<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Plugin\Condition;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
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
    \Drupal::service('theme_installer')->install(['test_theme']);

    $manager = \Drupal::service('plugin.manager.condition');
    /** @var \Drupal\Core\Condition\ConditionInterface $condition */
    $condition = $manager->createInstance('current_theme');
    $condition->setConfiguration(['theme' => 'test_theme']);
    /** @var \Drupal\Core\Condition\ConditionInterface $condition_any */
    $condition_any = $manager->createInstance('current_theme');
    $condition_any->setConfiguration(['theme' => '']);
    /** @var \Drupal\Core\Condition\ConditionInterface $condition_negated */
    $condition_negated = $manager->createInstance('current_theme');
    $condition_negated->setConfiguration(['theme' => 'test_theme', 'negate' => TRUE]);
    /** @var \Drupal\Core\Condition\ConditionInterface $condition_any_negated */
    $condition_any_negated = $manager->createInstance('current_theme');
    $condition_any_negated->setConfiguration(['theme' => '', 'negate' => TRUE]);

    $this->assertEquals(new FormattableMarkup('The current theme is @theme.', ['@theme' => 'test_theme']), $condition->summary());
    $this->assertEquals(new FormattableMarkup('The current theme is one of the enabled themes.', ['@theme' => '']), $condition_any->summary());
    $this->assertEquals(new FormattableMarkup('The current theme is not @theme.', ['@theme' => 'test_theme']), $condition_negated->summary());
    $this->assertEquals(new FormattableMarkup('The current theme is not one of the enabled themes.', ['@theme' => '']), $condition_any_negated->summary());

    // The expected theme has not been set up yet.
    $this->assertFalse($condition->execute());
    $this->assertTrue($condition_negated->execute());

    // Set the expected theme to be used.
    $this->config('system.theme')->set('default', 'test_theme')->save();
    \Drupal::theme()->resetActiveTheme();

    $this->assertTrue($condition->execute());
    $this->assertFalse($condition_negated->execute());
  }

}
