<?php

namespace Drupal\tabledrag_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for draggable table testing.
 *
 * If using this as a model for development, note that if you completely empty a
 * group, it will no longer set the group value correctly if you then try to add
 * items again (it sets the value based on a sibling). The core block list
 * builder uses a custom tabledrag.onDrop to set the correct region.
 */
class TableDragSubgroupTestForm extends FormBase {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a TableDragTestForm object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('state'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tabledrag_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['table'] = [
      '#type' => 'table',
      '#header' => [
        [
          'data' => $this->t('Text'),
          'colspan' => 5,
        ],
        $this->t('Weight'),
      ],
      '#attributes' => ['id' => 'tabledrag-test-table'],
      '#attached' => ['library' => ['tabledrag_test/tabledrag']],
    ];

    $groups = [
      'a' => $this->t('Group A'),
      'b' => $this->t('Group B'),
    ];

    // Provide a default set of five rows.
    $rows = $this->state->get('tabledrag_test_subgroup_table', array_flip(range(1, 5)));

    foreach ($rows as $id => $row) {
      if (!is_array($row)) {
        $rows[$id] = [];
      }
      // Set defaults
      $rows[$id] += [
        'parent' => '',
        'weight' => 0,
        'depth' => 0,
        'classes' => [],
        'draggable' => TRUE,
        'group' => $id < 3 ? 'a' : 'b',
      ];
    }

    // Organize by group.
    $by_group = [];
    foreach ($rows as $id => $row) {
      $by_group[$row['group']][$id] = $row;
    }

    // When dragging a row to be a child of another, the target to drag to is
    // different depending on whether you're dragging down or up. Create a
    // target span for both situations and style them into the appropriate
    // position.
    $drop_targets = [
      '<span data-drop-target-child-down>&nbsp;</span>',
      '<span data-drop-target-child-up>&nbsp;</span>',
    ];

    foreach ($by_group as $group => $group_rows) {
      $form['table']['#tabledrag'][] = [
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'tabledrag-test-weight',
        'subgroup' => 'tabledrag-test-weight-' . $group,
      ];
      $form['table']['#tabledrag'][] = [
        'action' => 'match',
        'relationship' => 'parent',
        'group' => 'tabledrag-test-parent',
        'subgroup' => 'tabledrag-test-parent-' . $group,
        'source' => 'tabledrag-test-id',
        'hidden' => TRUE,
        'limit' => 2,
      ];
      $form['table']['#tabledrag'][] = [
        'action' => 'depth',
        'relationship' => 'group',
        'group' => 'tabledrag-test-depth',
        'subgroup' => 'tabledrag-test-depth-' . $group,
        'hidden' => TRUE,
      ];
      $form['table']['#tabledrag'][] = [
        'action' => 'match',
        'relationship' => 'sibling',
        'group' => 'tabledrag-test-group',
        'subgroup' => 'tabledrag-test-group-' . $group,
      ];

      $form['table']['group-' . $group]['title'] = [
        '#plain_text' => $groups[$group],
        '#wrapper_attributes' => [
          'colspan' => 6,
        ],
      ];
      foreach ($group_rows as $id => $row) {
        if (!is_array($row)) {
          $row = [];
        }

        if (!empty($row['draggable'])) {
          $row['classes'][] = 'draggable';
        }

        $form['table'][$id] = [
          'title' => [
            'indentation' => [
              '#theme' => 'indentation',
              '#size' => $row['depth'],
            ],
            '#plain_text' => "Row with id $id",
            // Add the drag & drop targets.
            '#prefix' => implode('', $drop_targets),
          ],
          'id' => [
            '#type' => 'hidden',
            '#value' => $id,
            '#attributes' => ['class' => ['tabledrag-test-id']],
          ],
          'parent' => [
            '#type' => 'hidden',
            '#default_value' => $row['parent'],
            '#parents' => ['table', $id, 'parent'],
            '#attributes' => [
              'class' => [
                'tabledrag-test-parent', 'tabledrag-test-parent-' . $group,
              ],
            ],
          ],
          'depth' => [
            '#type' => 'hidden',
            '#default_value' => $row['depth'],
            '#attributes' => [
              'class' => [
                'tabledrag-test-depth', 'tabledrag-test-depth-' . $group,
              ],
            ],
          ],
          'group' => [
            '#type' => 'hidden',
            '#default_value' => $row['group'],
            '#attributes' => [
              'class' => [
                'tabledrag-test-group', 'tabledrag-test-group-' . $group,
              ],
            ],
          ],
          'weight' => [
            '#type' => 'weight',
            '#default_value' => $row['weight'],
            '#attributes' => [
              'class' => [
                'tabledrag-test-weight', 'tabledrag-test-weight-' . $group,
              ],
            ],
          ],
          '#attributes' => ['class' => $row['classes']],
        ];
      }
    }

    $form['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $test_table = [];
    foreach ($form_state->getValue('table') as $row) {
      $test_table[$row['id']] = $row;
    }
    $this->state->set('tabledrag_test_subgroup_table', $test_table);
  }

}
