<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\Core\Form\FormState;
use Drupal\views\Views;
use Drupal\views_ui\ViewUI;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests switching between style plugins in Views.
 *
 * @see \Drupal\views\Plugin\views\display\DisplayPluginBase::submitOptionsForm()
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class StylePluginSwitchTest extends PluginKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'views',
    'views_ui',
    'views_test_config',
    'views_test_data',
    'user',
  ];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_table'];

  /**
   * Tests that switching from Table to Grid style uses Grid's default options.
   *
   * This test verifies that when switching style plugins, the new plugin
   * uses its own default options rather than inheriting incompatible options
   * from the previous plugin.
   *
   * Without the fix in DisplayPluginBase::submitOptionsForm(), this test would
   * fail because the Table style's 'columns' option (an array mapping fields
   * to columns) would be passed to the Grid style, which expects 'columns' to
   * be an integer representing the number of grid columns.
   */
  public function testSwitchingFromTableToGridStyle(): void {
    $view = Views::getView('test_table');
    $view->setDisplay('default');
    $view->initDisplay();

    $display = $view->getDisplay();

    // Verify initial state: Table style with 'columns' as an array.
    $style_options = $display->getOption('style');
    $this->assertEquals('table', $style_options['type']);
    $this->assertIsArray($style_options['options']['columns']);

    // Wrap the view in ViewUI for proper form handling.
    $view_ui = new ViewUI($view->storage);

    // Simulate switching from Table to Grid style via form submission.
    $form = [];
    $form_state = new FormState();
    $form_state->set('section', 'style');
    $form_state->setValues([
      'style' => [
        'type' => 'grid',
      ],
    ]);
    $form_state->set('view', $view_ui);

    // Submit the form to trigger the style plugin switch.
    $display->submitOptionsForm($form, $form_state);

    // Verify that the style was switched to Grid.
    $style_options = $display->getOption('style');
    $this->assertEquals('grid', $style_options['type']);

    // Critical assertion: Grid's 'columns' option should be a string/integer
    // (the default is '4'), NOT an array inherited from the Table style.
    $this->assertArrayHasKey('columns', $style_options['options']);
    $columns = $style_options['options']['columns'];

    // Without the fix, this would fail because $columns would be an array
    // like ['age' => 'age', 'id' => 'id', ...] from the Table style.
    $this->assertIsNotArray(
      $columns,
      "Grid style 'columns' option should not be an array. Got: " . print_r($columns, TRUE)
    );

    // Verify it's the Grid's default value (string '4' or int 4).
    $this->assertEquals(
      '4',
      $columns,
      "Grid style 'columns' option should be the default value '4'."
    );
  }

  /**
   * Tests that switching from Grid to Table style uses Table's default options.
   *
   * This is the reverse test to ensure the fix works in both directions.
   */
  public function testSwitchingFromGridToTableStyle(): void {
    $view = Views::getView('test_table');
    $view->setDisplay('default');
    $view->initDisplay();

    $display = $view->getDisplay();

    // Wrap the view in ViewUI for proper form handling.
    $view_ui = new ViewUI($view->storage);

    // First, switch to Grid style.
    $form = [];
    $form_state = new FormState();
    $form_state->set('section', 'style');
    $form_state->setValues([
      'style' => [
        'type' => 'grid',
      ],
    ]);
    $form_state->set('view', $view_ui);
    $display->submitOptionsForm($form, $form_state);

    // Verify we're now on Grid.
    $style_options = $display->getOption('style');
    $this->assertEquals('grid', $style_options['type']);

    // Now switch back to Table style.
    $form_state = new FormState();
    $form_state->set('section', 'style');
    $form_state->setValues([
      'style' => [
        'type' => 'table',
      ],
    ]);
    $form_state->set('view', $view_ui);
    $display->submitOptionsForm($form, $form_state);

    // Verify the style was switched to Table.
    $style_options = $display->getOption('style');
    $this->assertEquals('table', $style_options['type']);

    // Critical assertion: Table's 'columns' option should be an array
    // (the default is []), NOT a string/integer from the Grid style.
    $this->assertArrayHasKey('columns', $style_options['options']);
    $columns = $style_options['options']['columns'];

    // Without the fix, this might fail if Grid's integer 'columns' was passed.
    $this->assertIsArray(
      $columns,
      "Table style 'columns' option should be an array."
    );
  }

}
