<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Functional\Update;

use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests post update functions for recalculating display dependencies.
 */
#[CoversFunction('field_post_update_recalculate_entity_form_display_dependencies')]
#[CoversFunction('field_post_update_recalculate_entity_view_display_dependencies')]
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class RecalculateDependenciesUpdateTest extends UpdatePathTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->applyRecipe('core/modules/field/tests/fixtures/recipes/invalid_display_dependencies');
    // The recipe application recalculates the dependencies, so we have to
    // mangle them manually.
    $this->config('core.entity_form_display.node.dynamic_dependencies.default')
      ->set('dependencies.module', [
        'field_dynamic_dependencies_test',
        'locale',
      ])
      ->save();
    $this->config('core.entity_view_display.node.dynamic_dependencies.default')
      ->set('dependencies.module', [
        'field_dynamic_dependencies_test',
        'locale',
        'user',
      ])
      ->save();
  }

  /**
   * Tests recalculating and updating entity display dependencies.
   */
  public function testPostUpdate(): void {
    $entity_type_manager = \Drupal::entityTypeManager();
    $form_display_storage = $entity_type_manager->getStorage('entity_form_display');
    $view_display_storage = $entity_type_manager->getStorage('entity_view_display');

    $form_dependencies_1 = $form_display_storage->load('node.dynamic_dependencies.default')
      ->getDependencies();
    $this->assertNotContains('language', $form_dependencies_1['module']);
    $this->assertContains('locale', $form_dependencies_1['module']);
    $view_dependencies_1 = $view_display_storage->load('node.dynamic_dependencies.default')
      ->getDependencies();
    $this->assertNotContains('language', $view_dependencies_1['module']);
    $this->assertContains('locale', $form_dependencies_1['module']);

    $this->runUpdates();

    $form_dependencies_2 = $form_display_storage->load('node.dynamic_dependencies.default')
      ->getDependencies();
    $this->assertContains('language', $form_dependencies_2['module']);
    $this->assertContains('locale', $form_dependencies_2['module']);
    $view_dependencies_2 = $view_display_storage->load('node.dynamic_dependencies.default')
      ->getDependencies();
    $this->assertContains('language', $view_dependencies_2['module']);
    $this->assertContains('locale', $view_dependencies_2['module']);
  }

}
