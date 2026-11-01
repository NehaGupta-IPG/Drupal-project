<?php

declare(strict_types=1);

namespace Drupal\ajax_forms_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\ajax_forms_test\Callbacks;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Builds a form where each Form API element triggers a simple Ajax callback.
 *
 * @internal
 */
class AjaxFormsTestAjaxElementsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ajax_forms_test_ajax_elements_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['date'] = [
      '#type' => 'date',
      '#ajax' => [
        'callback' => [Callbacks::class, 'dateCallback'],
      ],
      '#suffix' => '<div id="ajax_date_value">No date yet selected</div>',
    ];

    $form['datetime'] = [
      '#type' => 'datetime',
      '#ajax' => [
        'callback' => [Callbacks::class, 'datetimeCallback'],
        'wrapper' => 'ajax_datetime_value',
      ],
    ];

    $form['datetime_result'] = [
      '#type' => 'markup',
      '#markup' => '<div id="ajax_datetime_value">No datetime selected.</div>',
    ];

    $form['outside_table_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Outside button'),
      '#ajax' => [
        'callback' => [$callback_object, 'outsideTableCallback'],
      ],
    ];
    $form['information'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Column 1'),
        $this->t('Column 2'),
        $this->t('Column 3'),
      ],
      '#rows' => [
        [
          'column_1' => [
            'data' => [
              '#type' => 'button',
              '#value' => $this->t('Inside button'),
              '#attributes' => [
                'id' => ['edit-inside-table-button'],
              ],
              '#ajax' => [
                'callback' => [$callback_object, 'insideTableCallback'],
              ],
            ],
          ],
          'column_2' => [
            'data' => [
              '#markup' => '<div id="ajax_table_row_result">No table row result</div>',
            ],
          ],
          'column_3' => [
            'data' => [
              '#type' => 'link',
              '#title' => 'Open Modal',
              '#url' => Url::fromRoute('ajax_forms_test.dialog_form_link'),
              '#attributes' => [
                'class' => [
                  'use-ajax',
                ],
                'data-dialog-type' => 'modal',
                'data-dialog-options' => json_encode([
                  'width' => 600,
                ]),
              ],
              '#attached' => [
                'library' => [
                  'core/drupal.dialog.ajax',
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
