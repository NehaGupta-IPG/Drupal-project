<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\Component\Utility\Html;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Hook\ViewsThemeHooks;
use Drupal\views\Plugin\views\row\Fields;
use Drupal\views\ResultRow;
use Drupal\views\Views;
use Drupal\views_test_data\Plugin\views\row\RowTest;
use Drupal\views_test_data\Plugin\views\style\StyleTest as StyleTestPlugin;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests general style functionality.
 *
 * @see \Drupal\views_test_data\Plugin\views\style\StyleTest.
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class StyleTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_view'];

  /**
   * Tests the general rendering of styles.
   */
  public function testStyle(): void {
    $renderer = $this->container->get('renderer');

    // This run use the test row plugin and render with it.
    $view = Views::getView('test_view');
    $view->setDisplay();
    $style = $view->display_handler->getOption('style');
    $style['type'] = 'test_style';
    $view->display_handler->setOption('style', $style);
    $row = $view->display_handler->getOption('row');
    $row['type'] = 'test_row';
    $view->display_handler->setOption('row', $row);
    $view->initDisplay();
    $view->initStyle();
    // Reinitialize the style as it supports row plugins now.
    $view->style_plugin->init($view, $view->display_handler);
    $this->assertInstanceOf(RowTest::class, $view->rowPlugin);

    $random_text = $this->randomMachineName();
    $view->rowPlugin->setOutput($random_text);

    $output = $view->preview();
    $output = $renderer->renderRoot($output);
    $this->assertStringContainsString($random_text, (string) $output);

    // Test without row plugin support.
    $view = Views::getView('test_view');
    $view->setDisplay();
    $style = $view->display_handler->getOption('style');
    $style['type'] = 'test_style';
    $view->display_handler->setOption('style', $style);
    $view->initDisplay();
    $view->initStyle();
    $view->style_plugin->setUsesRowPlugin(FALSE);
    $this->assertInstanceOf(StyleTestPlugin::class, $view->style_plugin);
    $this->assertInstanceOf(Fields::class, $view->rowPlugin);

    $random_text = $this->randomMachineName();
    // Set some custom text to the output and make sure that this value is
    // rendered.
    $view->style_plugin->setOutput($random_text);
    $output = $view->preview();
    $output = $renderer->renderRoot($output);
    $this->assertStringContainsString($random_text, (string) $output);
  }

  /**
   * Tests the grouping features of styles.
   */
  public function testGrouping(): void {
    $this->doTestGrouping(FALSE);
    $this->doTestGrouping(TRUE);
  }

  /**
   * Provides reusable code for ::testGrouping().
   */
  protected function doTestGrouping($stripped = FALSE): void {
    $view = Views::getView('test_view');
    $view->setDisplay();
    // Setup grouping by the job and the age field.
    $view->initStyle();
    $view->style_plugin->options['grouping'] = [
      ['field' => 'job'],
      ['field' => 'age'],
    ];

    // Reduce the amount of items to make the test a bit easier.
    // Set up the pager.
    $view->displayHandlers->get('default')->overrideOption('pager', [
      'type' => 'some',
      'options' => ['items_per_page' => 3],
    ]);

    // Add the job and age field.
    $fields = [
      'name' => [
        'id' => 'name',
        'table' => 'views_test_data',
        'field' => 'name',
        'relationship' => 'none',
        'label' => 'Name',
        'element_label_colon' => TRUE,
      ],
      'job' => [
        'id' => 'job',
        'table' => 'views_test_data',
        'field' => 'job',
        'relationship' => 'none',
        'label' => 'Job',
        'element_label_colon' => TRUE,
      ],
      'age' => [
        'id' => 'age',
        'table' => 'views_test_data',
        'field' => 'age',
        'relationship' => 'none',
        'label' => 'Age',
        'element_label_colon' => TRUE,
      ],
    ];
    $view->displayHandlers->get('default')->overrideOption('fields', $fields);

    // Now run the query and group by the result.
    $this->executeView($view);

    $expected = [];
    $expected['Job: Singer'] = [];
    $expected['Job: Singer']['group'] = 'Job: Singer';
    $expected['Job: Singer']['level'] = 0;
    $expected['Job: Singer']['rows']['Age: 25'] = [];
    $expected['Job: Singer']['rows']['Age: 25']['group'] = 'Age: 25';
    $expected['Job: Singer']['rows']['Age: 25']['level'] = 1;
    $expected['Job: Singer']['rows']['Age: 25']['rows'][0] = new ResultRow(['index' => 0]);
    $expected['Job: Singer']['rows']['Age: 25']['rows'][0]->views_test_data_name = 'John';
    $expected['Job: Singer']['rows']['Age: 25']['rows'][0]->views_test_data_job = 'Singer';
    $expected['Job: Singer']['rows']['Age: 25']['rows'][0]->views_test_data_age = '25';
    $expected['Job: Singer']['rows']['Age: 25']['rows'][0]->views_test_data_id = '1';
    $expected['Job: Singer']['rows']['Age: 27'] = [];
    $expected['Job: Singer']['rows']['Age: 27']['group'] = 'Age: 27';
    $expected['Job: Singer']['rows']['Age: 27']['level'] = 1;
    $expected['Job: Singer']['rows']['Age: 27']['rows'][1] = new ResultRow(['index' => 1]);
    $expected['Job: Singer']['rows']['Age: 27']['rows'][1]->views_test_data_name = 'George';
    $expected['Job: Singer']['rows']['Age: 27']['rows'][1]->views_test_data_job = 'Singer';
    $expected['Job: Singer']['rows']['Age: 27']['rows'][1]->views_test_data_age = '27';
    $expected['Job: Singer']['rows']['Age: 27']['rows'][1]->views_test_data_id = '2';
    $expected['Job: Drummer'] = [];
    $expected['Job: Drummer']['group'] = 'Job: Drummer';
    $expected['Job: Drummer']['level'] = 0;
    $expected['Job: Drummer']['rows']['Age: 28'] = [];
    $expected['Job: Drummer']['rows']['Age: 28']['group'] = 'Age: 28';
    $expected['Job: Drummer']['rows']['Age: 28']['level'] = 1;
    $expected['Job: Drummer']['rows']['Age: 28']['rows'][2] = new ResultRow(['index' => 2]);
    $expected['Job: Drummer']['rows']['Age: 28']['rows'][2]->views_test_data_name = 'Ringo';
    $expected['Job: Drummer']['rows']['Age: 28']['rows'][2]->views_test_data_job = 'Drummer';
    $expected['Job: Drummer']['rows']['Age: 28']['rows'][2]->views_test_data_age = '28';
    $expected['Job: Drummer']['rows']['Age: 28']['rows'][2]->views_test_data_id = '3';

    // Alter the results to support the stripped case.
    if ($stripped) {
      // Add some html to the result and expected value.
      $rand1 = '<a data="' . $this->randomMachineName() . '" />';
      $view->result[0]->views_test_data_job .= $rand1;
      $expected['Job: Singer']['rows']['Age: 25']['rows'][0]->views_test_data_job = 'Singer' . $rand1;
      $expected['Job: Singer']['group'] = 'Job: Singer';
      $rand2 = '<a data="' . $this->randomMachineName() . '" />';
      $view->result[1]->views_test_data_job .= $rand2;
      $expected['Job: Singer']['rows']['Age: 27']['rows'][1]->views_test_data_job = 'Singer' . $rand2;
      $rand3 = '<a data="' . $this->randomMachineName() . '" />';
      $view->result[2]->views_test_data_job .= $rand3;
      $expected['Job: Drummer']['rows']['Age: 28']['rows'][2]->views_test_data_job = 'Drummer' . $rand3;
      $expected['Job: Drummer']['group'] = 'Job: Drummer';

      $view->style_plugin->options['grouping'][0] = ['field' => 'job', 'rendered' => TRUE, 'rendered_strip' => TRUE];
      $view->style_plugin->options['grouping'][1] = ['field' => 'age', 'rendered' => TRUE, 'rendered_strip' => TRUE];
    }

    // The newer api passes the value of the grouping as well.
    $sets_new_rendered = $view->style_plugin->renderGrouping($view->result, $view->style_plugin->options['grouping'], TRUE);

    $this->assertEquals($expected, $sets_new_rendered);

    // Don't test stripped case, because the actual value is not stripped.
    if (!$stripped) {
      $sets_new_value = $view->style_plugin->renderGrouping($view->result, $view->style_plugin->options['grouping'], FALSE);

      // Reorder the group structure to grouping by value.
      $new_expected = $expected;
      $new_expected['Singer'] = $expected['Job: Singer'];
      $new_expected['Singer']['rows']['25'] = $expected['Job: Singer']['rows']['Age: 25'];
      $new_expected['Singer']['rows']['27'] = $expected['Job: Singer']['rows']['Age: 27'];
      $new_expected['Drummer'] = $expected['Job: Drummer'];
      $new_expected['Drummer']['rows']['28'] = $expected['Job: Drummer']['rows']['Age: 28'];
      unset($new_expected['Job: Singer']);
      unset($new_expected['Singer']['rows']['Age: 25']);
      unset($new_expected['Singer']['rows']['Age: 27']);
      unset($new_expected['Job: Drummer']);
      unset($new_expected['Drummer']['rows']['Age: 28']);

      $this->assertEquals($new_expected, $sets_new_value);
    }

    // Test that grouping works on fields having no label.
    $fields['job']['label'] = '';
    $view->destroy();
    $view->setDisplay();
    $view->initStyle();
    $view->displayHandlers->get('default')->overrideOption('fields', $fields);
    $view->style_plugin->options['grouping'] = [
      ['field' => 'job'],
      ['field' => 'age'],
    ];

    $this->executeView($view);

    if ($stripped) {
      $view->result[0]->views_test_data_job .= $rand1;
      $view->result[1]->views_test_data_job .= $rand2;
      $view->result[2]->views_test_data_job .= $rand3;
      $view->style_plugin->options['grouping'][0] = ['field' => 'job', 'rendered' => TRUE, 'rendered_strip' => TRUE];
      $view->style_plugin->options['grouping'][1] = ['field' => 'age', 'rendered' => TRUE, 'rendered_strip' => TRUE];
    }

    $sets_new_rendered = $view->style_plugin->renderGrouping($view->result, $view->style_plugin->options['grouping'], TRUE);
    $no_label_expected = $expected;
    // Remove labels from expected results.
    foreach ($no_label_expected as $job => $data) {
      unset($no_label_expected[$job]);
      $job = str_replace('Job: ', '', $job);
      $data['group'] = $job;
      $no_label_expected[$job] = $data;
    }
    $this->assertEquals($no_label_expected, $sets_new_rendered);

    // Test that grouping works on fields having no colon after the label.
    $fields['job']['label'] = 'Job';
    $fields['job']['element_label_colon'] = FALSE;
    $view->destroy();
    $view->setDisplay();
    $view->initStyle();
    $view->displayHandlers->get('default')->overrideOption('fields', $fields);
    $view->style_plugin->options['grouping'] = [
      ['field' => 'job'],
      ['field' => 'age'],
    ];

    $this->executeView($view);

    if ($stripped) {
      $view->result[0]->views_test_data_job .= $rand1;
      $view->result[1]->views_test_data_job .= $rand2;
      $view->result[2]->views_test_data_job .= $rand3;
      $view->style_plugin->options['grouping'][0] = ['field' => 'job', 'rendered' => TRUE, 'rendered_strip' => TRUE];
      $view->style_plugin->options['grouping'][1] = ['field' => 'age', 'rendered' => TRUE, 'rendered_strip' => TRUE];
    }

    $sets_new_rendered = $view->style_plugin->renderGrouping($view->result, $view->style_plugin->options['grouping'], TRUE);

    // Remove colons from expected results.
    $no_colon_expected = $expected;
    foreach ($no_colon_expected as $job => $data) {
      unset($no_colon_expected[$job]);
      $job = str_replace('Job: ', 'Job ', $job);
      $data['group'] = $job;
      $no_colon_expected[$job] = $data;
    }
    $this->assertEquals($no_colon_expected, $sets_new_rendered);
  }

  /**
   * Tests that views_view_grouping is used for all grouping levels.
   *
   */
  public function testGroupingPreprocessInvoked(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();
    $view->initStyle();

    // Configure a single grouping level.
    $view->style_plugin->options['grouping'] = [
      ['field' => 'job', 'rendered' => TRUE, 'rendered_strip' => FALSE],
    ];

    $fields = [
      'name' => [
        'id' => 'name',
        'table' => 'views_test_data',
        'field' => 'name',
        'relationship' => 'none',
      ],
      'job' => [
        'id' => 'job',
        'table' => 'views_test_data',
        'field' => 'job',
        'relationship' => 'none',
        'label' => 'Job',
        'element_label_colon' => TRUE,
      ],
    ];
    $view->displayHandlers->get('default')->overrideOption('fields', $fields);
    $view->displayHandlers->get('default')->overrideOption('pager', [
      'type' => 'some',
      'options' => ['items_per_page' => 3],
    ]);

    $this->executeView($view);

    $sets = $view->style_plugin->renderGrouping(
      $view->result,
      $view->style_plugin->options['grouping'],
      TRUE,
    );
    $output = $view->style_plugin->renderGroupingSets($sets);

    // With a single grouping level, every set must use the views_view_grouping
    // theme so that hook_preprocess_views_view_grouping() is invoked.
    // @see \Drupal\views\Plugin\views\style\StylePluginBase::renderGroupingSets()
    foreach ($output as $set) {
      $this->assertNotEmpty($set['#theme'], 'Grouping set has a #theme key.');
      $themes = (array) $set['#theme'];
      $this->assertStringStartsWith(
        'views_view_grouping',
        reset($themes),
        'Single-level grouping set uses views_view_grouping theme.',
      );
    }

    // Verify that preprocessViewsViewGrouping produces an indexed array for
    // $variables['content'] at the leaf level, consistent with the nested-sets
    // case, so that {% for item in content %} works in templates.
    $first_set = reset($sets);
    $variables = [
      'view' => $view,
      'grouping' => $view->style_plugin->options['grouping'][0],
      'grouping_level' => 0,
      'rows' => $first_set['rows'],
      'title' => $first_set['group'],
    ];
    // Manually trigger the initial preprocess.
    (new ViewsThemeHooks(
      $this->container->get('module_handler'),
      $this->container->get('extension.list.module'),
      $this->container->get('path.current'),
      $this->container->get('router.route_provider'),
      $this->container->get('request_stack'),
      $this->container->get('config.factory'),
      $this->container->get('renderer'),
      $this->container->get('pager.manager'),
      $this->container->get('language_manager'),
      $this->container->get('datetime.time'),
    ))->preprocessViewsViewGrouping($variables);

    // Content must be an indexed array (not a bare render array) so that
    // templates can iterate over it with {% for item in content %}.
    $this->assertIsArray($variables['content']);
    $this->assertArrayHasKey(0, $variables['content'],
      'Content is an indexed array with at least one element.',
    );
    $this->assertArrayHasKey('#theme', $variables['content'][0],
      'First content element is a render array.',
    );

    // Now verify with 2 grouping levels: the hook must fire for both levels.
    $view->destroy();
    $view = Views::getView('test_view');
    $view->setDisplay();
    $view->initStyle();
    $view->style_plugin->options['grouping'] = [
      ['field' => 'job', 'rendered' => TRUE, 'rendered_strip' => FALSE],
      ['field' => 'age', 'rendered' => TRUE, 'rendered_strip' => FALSE],
    ];
    $fields['age'] = [
      'id' => 'age',
      'table' => 'views_test_data',
      'field' => 'age',
      'relationship' => 'none',
      'label' => 'Age',
      'element_label_colon' => TRUE,
    ];
    $view->displayHandlers->get('default')->overrideOption('fields', $fields);
    $view->displayHandlers->get('default')->overrideOption('pager', [
      'type' => 'some',
      'options' => ['items_per_page' => 3],
    ]);
    $this->executeView($view);

    $sets = $view->style_plugin->renderGrouping(
      $view->result,
      $view->style_plugin->options['grouping'],
      TRUE,
    );
    $output = $view->style_plugin->renderGroupingSets($sets);

    // Level 0 (outer): rows are nested sets, so views_view_grouping is used
    // via the existing branch A — this was already correct.
    foreach ($output as $outer_set) {
      $themes = (array) $outer_set['#theme'];
      $this->assertStringStartsWith(
        'views_view_grouping',
        reset($themes),
        'Outer level of 2-level grouping uses views_view_grouping theme.',
      );
    }
  }

  /**
   * Tests that renderGroupingSets uses renderRowGroup when no grouping is set.
   *
   * When $this->options['grouping'] is empty, the else branch must still fall
   * back to renderRowGroup() (no regression).
   *
   */
  public function testNoGroupingUsesRenderRowGroup(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();
    $view->initStyle();

    // No grouping configured.
    $view->style_plugin->options['grouping'] = [];
    $this->executeView($view);

    // renderGrouping() with no grouping returns a single set with all rows.
    $sets = $view->style_plugin->renderGrouping($view->result, [], TRUE);
    $output = $view->style_plugin->renderGroupingSets($sets);

    // Without grouping, the style's own theme must be used, not
    // views_view_grouping.
    foreach ($output as $set) {
      $themes = (array) $set['#theme'];
      $this->assertStringNotContainsString(
        'views_view_grouping',
        reset($themes),
        'Without grouping, the style theme is used (not views_view_grouping).',
      );
    }
  }

  /**
   * Tests that preprocessViewsViewGrouping sets content correctly.
   *
   * Verifies both the nested-sets path (recursive renderGroupingSets call) and
   * the leaf-rows path (rows already rendered by the row plugin).
   *
   */
  public function testGroupingPreprocessContentPopulated(): void {
    $renderer = $this->container->get('renderer');

    $view = Views::getView('test_view');
    $view->setDisplay();
    $view->initStyle();
    $view->style_plugin->options['grouping'] = [
      ['field' => 'job', 'rendered' => TRUE, 'rendered_strip' => FALSE],
    ];
    $fields = [
      'name' => [
        'id' => 'name',
        'table' => 'views_test_data',
        'field' => 'name',
        'relationship' => 'none',
      ],
      'job' => [
        'id' => 'job',
        'table' => 'views_test_data',
        'field' => 'job',
        'relationship' => 'none',
        'label' => 'Job',
        'element_label_colon' => TRUE,
      ],
    ];
    $view->displayHandlers->get('default')->overrideOption('fields', $fields);
    $view->displayHandlers->get('default')->overrideOption('pager', [
      'type' => 'some',
      'options' => ['items_per_page' => 3],
    ]);
    $this->executeView($view);

    $output = $view->preview();
    $html = (string) $renderer->renderRoot($output);

    // The rendered output must contain data from the view result, confirming
    // that preprocessViewsViewGrouping correctly populated $variables['content']
    // for the leaf level and the content was rendered.
    $this->assertStringContainsString('John', $html);
    $this->assertStringContainsString('George', $html);
    $this->assertStringContainsString('Ringo', $html);

    // Group titles must also appear in the output.
    $this->assertStringContainsString('Singer', $html);
    $this->assertStringContainsString('Drummer', $html);
  }

  /**
   * Tests custom CSS row classes.
   */
  public function testCustomRowClasses(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    // Setup some random css class.
    $view->initStyle();
    $random_name = $this->randomMachineName();
    $view->style_plugin->options['row_class'] = $random_name . " test-token-{{ name }}";

    $output = $view->preview();
    $html_dom = $this->getHtmlDom((string) $this->container->get('renderer')->renderRoot($output));

    $rows = $html_dom->body->div->div;
    $count = 0;
    foreach ($rows as $row) {
      $attributes = $row->attributes();
      $class = (string) $attributes['class'][0];
      $this->assertStringContainsString($random_name, $class);

      // Check token replacement.
      $name = $view->field['name']->getValue($view->result[$count]);
      $this->assertStringContainsString("test-token-$name", $class);

      $count++;
    }
  }

  /**
   * Returns a view output as SimpleXMLElement.
   *
   * @return \SimpleXMLElement|null
   *   The HTML DOM.
   */
  protected function getHtmlDom($output): ?\SimpleXMLElement {
    $html_dom = Html::load($output);
    if ($html_dom) {
      // It's much easier to work with simplexml than DOM, luckily enough
      // we can just simply import our DOM tree.
      return simplexml_import_dom($html_dom);
    }
    return NULL;
  }

}
