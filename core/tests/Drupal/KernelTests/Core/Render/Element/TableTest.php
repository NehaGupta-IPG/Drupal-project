<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Render\Element;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests built-in table theme functions.
 */
#[Group('Theme')]
#[RunTestsInSeparateProcesses]
class TableTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'form_test'];

  /**
   * Tests that states are applied to table cells.
   */
  public function testStatesOnCells(): void {
    $states = [
      'visible' => [
        ':input[name="toggle"]' => ['checked' => TRUE],
      ],
    ];
    $tables = [
      'table render element' => [
        'tag' => 'td',
        'build' => [
          '#type' => 'table',
          'row' => [
            'cell' => [
              '#markup' => 'Target cell',
              '#states' => $states,
            ],
          ],
        ],
      ],
      'table render element with plain text' => [
        'tag' => 'td',
        'build' => [
          '#type' => 'table',
          'row' => [
            'cell' => [
              '#plain_text' => 'Target cell',
              '#states' => $states,
            ],
          ],
        ],
      ],
      'table theme' => [
        'tag' => 'td',
        'build' => [
          '#theme' => 'table',
          '#rows' => [
            [
              [
                'data' => [
                  '#markup' => 'Target cell',
                  '#states' => $states,
                ],
              ],
            ],
          ],
        ],
      ],
      'table header' => [
        'tag' => 'th',
        'build' => [
          '#theme' => 'table',
          '#header' => [
            [
              'data' => [
                '#markup' => 'Target cell',
                '#states' => $states,
              ],
            ],
          ],
        ],
      ],
    ];

    foreach ($tables as $label => ['tag' => $tag, 'build' => $table]) {
      $markup = $this->render($table);
      $xpath = new \DOMXPath(Html::load($markup));
      $cell = $xpath->query("//{$tag}[@data-drupal-states]")->item(0);
      $this->assertNotNull($cell, "$label has states data on the table cell.");
      $this->assertContains('js-form-wrapper', explode(' ', $cell->getAttribute('class')));
      $this->assertSame($states, Json::decode($cell->getAttribute('data-drupal-states')));
      $this->assertStringContainsString('core/misc/states.js', $markup);
    }
  }

  /**
   * Tests that states remain on form elements within table cells.
   */
  public function testStatesOnFormElementInCell(): void {
    $states = [
      'required' => [
        ':input[name="toggle"]' => ['checked' => TRUE],
      ],
    ];
    $table = [
      '#type' => 'table',
      'row' => [
        'cell' => [
          '#type' => 'textfield',
          '#title' => 'Target field',
          '#states' => $states,
        ],
      ],
    ];

    $markup = $this->render($table);
    $xpath = new \DOMXPath(Html::load($markup));
    $cell = $xpath->query('//td')->item(0);
    $this->assertNotNull($cell);
    $this->assertFalse($cell->hasAttribute('data-drupal-states'));
    $input = $xpath->query('//input')->item(0);
    $this->assertNotNull($input);
    $this->assertSame($states, Json::decode($input->getAttribute('data-drupal-states')));
  }

  /**
   * If $sticky is TRUE, `sticky-header` class should be included.
   */
  public function testThemeTableStickyHeaders(): void {
    $header = ['one', 'two', 'three'];
    $rows = [[1, 2, 3], [4, 5, 6], [7, 8, 9]];
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#sticky' => TRUE,
    ];
    $this->render($table);
    $this->assertRaw('sticky-header');
  }

  /**
   * If $sticky is FALSE, `sticky-header` class should not be included.
   */
  public function testThemeTableNoStickyHeaders(): void {
    $header = ['one', 'two', 'three'];
    $rows = [[1, 2, 3], [4, 5, 6], [7, 8, 9]];
    $attributes = [];
    $caption = NULL;
    $colgroups = [];
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => $attributes,
      '#caption' => $caption,
      '#colgroups' => $colgroups,
      '#sticky' => FALSE,
    ];
    $this->render($table);
    $this->assertNoRaw('sticky-header');
  }

  /**
   * Tests the display of the table header.
   *
   * Tests are performed when the there are no rows and that the empty text is
   * displayed correctly.
   */
  public function testThemeTableWithEmptyMessage(): void {
    $header = [
      'Header 1',
      [
        'data' => 'Header 2',
        'colspan' => 2,
      ],
    ];
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => [],
      '#empty' => 'Empty row.',
    ];

    // Enable the Starterkit theme.
    \Drupal::service('theme_installer')->install(['starterkit_theme']);
    $this->config('system.theme')->set('default', 'starterkit_theme')->save();

    $this->render($table);
    $this->removeWhiteSpace();
    $this->assertRaw('<thead><tr><th>Header 1</th><th colspan="2">Header 2</th></tr>', 'Table header found.');
    $this->assertRaw('<tr class="odd"><td colspan="3" class="empty message">Empty row.</td>', 'Colspan on #empty row found.');
  }

  /**
   * Tests that the 'no_striping' option works correctly.
   */
  public function testThemeTableWithNoStriping(): void {
    $rows = [
      [
        'data' => [1],
        'no_striping' => TRUE,
      ],
    ];
    $table = [
      '#type' => 'table',
      '#rows' => $rows,
    ];
    $this->render($table);
    $this->assertNoRaw('class="odd"', 'Odd/even classes were not added because $no_striping = TRUE.');
    $this->assertNoRaw('no_striping', 'No invalid no_striping HTML attribute was printed.');
  }

  /**
   * Tests that the 'footer' option works correctly.
   */
  public function testThemeTableFooter(): void {
    $footer = [
      [
        'data' => [1],
      ],
      ['Foo'],
    ];

    $table = [
      '#type' => 'table',
      '#rows' => [],
      '#footer' => $footer,
    ];

    $this->render($table);
    $this->removeWhiteSpace();
    $this->assertRaw('<tfoot><tr><td>1</td></tr><tr><td>Foo</td></tr></tfoot>', 'Table footer found.');
  }

  /**
   * Tests that the 'header' option in cells works correctly.
   */
  public function testThemeTableHeaderCellOption(): void {
    $rows = [
      [
        ['data' => 1, 'header' => TRUE],
        ['data' => 1, 'header' => FALSE],
        ['data' => 1],
      ],
    ];
    $table = [
      '#type' => 'table',
      '#rows' => $rows,
    ];
    $this->render($table);
    $this->removeWhiteSpace();
    $this->assertRaw('<th>1</th><td>1</td><td>1</td>', 'The th and td tags was printed correctly.');
  }

  /**
   * Tests that the 'responsive-table' class is applied correctly.
   */
  public function testThemeTableResponsive(): void {
    $header = ['one', 'two', 'three'];
    $rows = [[1, 2, 3], [4, 5, 6], [7, 8, 9]];
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#responsive' => TRUE,
    ];
    $this->render($table);
    $this->assertRaw('responsive-enabled', 'The responsive-enabled class was printed correctly.');
  }

  /**
   * Tests that the 'responsive-table' class is not applied without headers.
   */
  public function testThemeTableNotResponsiveHeaders(): void {
    $rows = [[1, 2, 3], [4, 5, 6], [7, 8, 9]];
    $table = [
      '#type' => 'table',
      '#rows' => $rows,
      '#responsive' => TRUE,
    ];
    $this->render($table);
    $this->assertNoRaw('responsive-enabled', 'The responsive-enabled class is not applied without table headers.');
  }

  /**
   * Tests that 'responsive-table' class only applied when responsive is TRUE.
   */
  public function testThemeTableNotResponsiveProperty(): void {
    $header = ['one', 'two', 'three'];
    $rows = [[1, 2, 3], [4, 5, 6], [7, 8, 9]];
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#responsive' => FALSE,
    ];
    $this->render($table);
    $this->assertNoRaw('responsive-enabled', 'The responsive-enabled class is not applied without the "responsive" property set to TRUE.');
  }

  /**
   * Tests 'priority-medium' and 'priority-low' classes.
   */
  public function testThemeTableResponsivePriority(): void {
    $header = [
      // Test associative header indices.
      'associative_key' => ['data' => 1, 'class' => [RESPONSIVE_PRIORITY_MEDIUM]],
      // Test non-associative header indices.
      ['data' => 2, 'class' => [RESPONSIVE_PRIORITY_LOW]],
      // Test no responsive priorities.
      ['data' => 3],
    ];
    $rows = [[4, 5, 6]];
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#responsive' => TRUE,
    ];
    $this->render($table);
    $this->assertRaw('<th class="priority-medium">1</th>', 'Header 1: the priority-medium class was applied correctly.');
    $this->assertRaw('<th class="priority-low">2</th>', 'Header 2: the priority-low class was applied correctly.');
    $this->assertRaw('<th>3</th>', 'Header 3: no priority classes were applied.');
    $this->assertRaw('<td class="priority-medium">4</td>', 'Cell 1: the priority-medium class was applied correctly.');
    $this->assertRaw('<td class="priority-low">5</td>', 'Cell 2: the priority-low class was applied correctly.');
    $this->assertRaw('<td>6</td>', 'Cell 3: no priority classes were applied.');
  }

  /**
   * Tests header elements with a mix of string and render array values.
   */
  public function testThemeTableHeaderRenderArray(): void {
    $header = [
      [
        'data' => [
          '#markup' => 'one',
        ],
      ],
      'two',
      [
        'data' => [
          '#type' => 'html_tag',
          '#tag' => 'b',
          '#value' => 'three',
        ],
      ],
    ];
    $rows = [[1, 2, 3], [4, 5, 6], [7, 8, 9]];
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#responsive' => FALSE,
    ];
    $this->render($table);
    $this->removeWhiteSpace();
    $this->assertRaw('<thead><tr><th>one</th><th>two</th><th><b>three</b></th></tr>', 'Table header found.');
  }

  /**
   * Tests row elements with a mix of string and render array values.
   */
  public function testThemeTableRowRenderArray(): void {
    $header = ['one', 'two', 'three'];
    $rows = [
      [
        '1-one',
        [
          'data' => '1-two',
        ],
        '1-three',
      ],
      [
        [
          'data' => [
            '#markup' => '2-one',
          ],
        ],
        '2-two',
        [
          'data' => [
            '#type' => 'html_tag',
            '#tag' => 'b',
            '#value' => '2-three',
          ],
        ],
      ],
    ];
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#responsive' => FALSE,
    ];
    $this->render($table);
    $this->removeWhiteSpace();
    $this->assertRaw('<tbody><tr><td>1-one</td><td>1-two</td><td>1-three</td></tr>', 'Table row 1 found.');
    $this->assertRaw('<tr><td>2-one</td><td>2-two</td><td><b>2-three</b></td></tr></tbody>', 'Table row 2 found.');
  }

  /**
   * Tests that the select/checkbox label is being generated and escaped.
   */
  public function testThemeTableTitle(): void {
    $form = \Drupal::formBuilder()->getForm('\Drupal\form_test\Form\FormTestTableForm');
    $this->render($form);
    $this->assertEscaped('Update <em>kitten</em>');
    $this->assertRaw('Update my favorite fruit is <strong>bananas</strong>');
  }

}
