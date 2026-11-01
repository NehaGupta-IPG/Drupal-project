<?php

namespace Drupal\KernelTests\Core\Form;

use Drupal\Core\Form\JsonSchemaFormBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * @coversDefaultClass \Drupal\Core\Form\JsonSchemaFormBuilder
 * @group Form
 */
class JsonSchemaFormBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The JSON Schema form builder.
   *
   * @var \Drupal\Core\Form\JsonSchemaFormBuilder
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('system');

    $this->formBuilder = new JsonSchemaFormBuilder();
  }

  /**
   * @dataProvider providerTestJsonSchemaForms
   */
  public function testJsonSchemaForms($form_arg, $expected): void {
    $form = \Drupal::formBuilder()->getForm($form_arg);
    $actual = $this->formBuilder->build($form);

    $this->assertArrayHasKey('formData', $actual);
    $this->assertArrayHasKey('form_build_id', $actual['formData']);
    // Copy the generated form build ID into the expected data.
    $expected['formData']['form_build_id'] = $actual['formData']['form_build_id'];

    $this->assertEquals($expected, $actual);
    $this->assertSame($expected, $actual);
  }

  /**
   * Provides test data for ::testJsonSchemaForms().
   */
  public static function providerTestJsonSchemaForms(): array {
    $data = [];
    $data[] = [
      TestForm::class,
      [
        'schema' => [
          'type' => 'object',
          'title' => 'The form title',
          'properties' => [
            'an_item' => [
              'type' => 'string',
              'title' => 'An item',
            ],
            'just_some_markup' => [
              'type' => 'string',
            ],
            'section' => [
              'type' => 'object',
              'title' => '',
              'properties' => [
                'test1' => [
                  'type' => 'string',
                  'title' => 'Test 1',
                ],
                'test2' => [
                  'type' => 'boolean',
                  'title' => 'Test 2',
                ],
              ],
              'required' => [
                'test1',
                'test2',
              ],
            ],
            'all_the_things' => [
              'type' => 'object',
              'title' => '',
              'properties' => [
                'checkbox' => [
                  'type' => 'boolean',
                ],
                'checkboxes' => [
                  'items' => [
                    'type' => 'string',
                    'enum' => [
                      'foo',
                      'bar',
                    ],
                    'enumNames' => [
                      'Foo',
                      'Bar',
                    ],
                  ],
                  'uniqueItems' => TRUE,
                  'type' => 'array',
                ],
                'color' => [
                  'type' => 'string',
                ],
                'date' => [
                  'type' => 'string',
                ],
                'email' => [
                  'type' => 'string',
                ],
                'hidden' => [
                  'type' => 'string',
                ],
                'item' => [
                  'type' => 'string',
                ],
                'link' => [
                  'type' => 'string',
                ],
                'number' => [
                  'type' => 'number',
                ],
                'password' => [
                  'type' => 'string',
                ],
                'path' => [
                  'type' => 'string',
                ],
                'radios' => [
                  'type' => 'string',
                  'enum' => [
                    'foo',
                    'bar',
                  ],
                  'enumNames' => [
                    'Foo',
                    'Bar',
                  ],
                ],
                'range' => [
                  'type' => 'number',
                ],
                'search' => [
                  'type' => 'string',
                ],
                'select' => [
                  'type' => 'string',
                  'enum' => [
                    'foo',
                    'bar',
                  ],
                  'enumNames' => [
                    'Foo',
                    'Bar',
                  ],
                ],
                'submit' => [
                  'type' => 'string',
                ],
                'tel' => [
                  'type' => 'string',
                ],
                'textarea' => [
                  'type' => 'string',
                ],
                'textfield' => [
                  'type' => 'string',
                ],
                'token' => [
                  'type' => 'string',
                ],
                'url' => [
                  'type' => 'string',
                ],
                'vertical_tabs' => [
                  'type' => 'object',
                  'title' => 'Vertical Tabs',
                  'properties' => [
                    'vertical_tabs__active_tab' => [
                      'type' => 'string',
                    ],
                  ],
                ],
                'weight' => [
                  'type' => 'integer',
                  'enum' => range(-10, 10),
                ],
              ],
            ],
            'actions' => [
              'type' => 'object',
              'title' => '',
              'properties' => [
                'submit' => [
                  'type' => 'string',
                ],
              ],
            ],
            'form_build_id' => [
              'type' => 'string',
            ],
            'form_id' => [
              'type' => 'string',
            ],
          ],
        ],
        'uiSchema' => [
          'an_item' => [
            'ui:widget' => 'item',
            'ui:options' => ['markup' => 'This is the item'],
          ],
          'just_some_markup' => [
            'ui:options' => [
              'label' => FALSE,
              'markup' => '<p>Test paragraph</p>',
            ],
            'ui:widget' => 'markup',
          ],
          'section' => [
            'test1' => [
              'ui:description' => 'This is the description of Test 1',
            ],
            'test2' => [
              'ui:widget' => 'checkbox',
              'ui:options' => [
                'label' => FALSE,
              ],
            ],
          ],
          'all_the_things' => [
            'checkbox' => [
              'ui:widget' => 'checkbox',
              'ui:options' => [
                'label' => FALSE,
              ],
            ],
            'checkboxes' => [
              'ui:widget' => 'checkboxes',
            ],
            'hidden' => [
              'ui:widget' => 'hidden',
            ],
            'item' => [
              'ui:widget' => 'item',
            ],
            'link' => [
              'ui:widget' => 'link',
              'ui:options' => [
                'label' => FALSE,
              ],
            ],
            'radios' => [
              'ui:widget' => 'radio',
            ],
            'submit' => [
              'ui:widget' => 'submit',
              'ui:options' => [
                'label' => FALSE,
                'name' => 'op',
              ],
            ],
            'token' => [
              'ui:widget' => 'hidden',
            ],
            'value' => [
              'ui:widget' => 'hidden',
            ],
            'vertical_tabs' => [
              'vertical_tabs__active_tab' => [
                'ui:widget' => 'hidden',
              ],
            ],
          ],
          'actions' => [
            'submit' => [
              'ui:widget' => 'submit',
              'ui:options' => [
                'label' => FALSE,
                'name' => 'op',
              ],
            ],
          ],
          'form_build_id' => [
            'ui:widget' => 'hidden',
          ],
          'form_id' => [
            'ui:widget' => 'hidden',
          ],
        ],
        'formData' => [
          'section' => [
            'test2' => FALSE,
          ],
          'all_the_things' => [
            'checkbox' => 0,
            'weight' => 0,
          ],
          'actions' => [
            'submit' => 'Save configuration',
          ],
          'form_build_id' => '',
          'form_id' => 'test_form',
        ],
      ],
    ];
    return $data;
  }

}

class TestForm implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#title'] = 'The form title';

    $form['an_item'] = [
      '#type' => 'item',
      '#title' => 'An item',
      '#plain_text' => 'This is the item',
    ];

    $form['just_some_markup'] = [
      '#type' => 'markup',
      '#markup' => '<p>Test paragraph</p>',
    ];

    // Test nested fields.
    $form['section']['test1'] = [
      '#type' => 'textfield',
      '#title' => 'Test 1',
      '#required' => TRUE,
      '#description' => 'This is the description of Test 1',
    ];
    $form['section']['test2'] = [
      '#type' => 'checkbox',
      '#title' => 'Test 2',
      '#required' => TRUE,
      '#default_value' => FALSE,
    ];

    // Test every basic element type, skipping ones with known problems.
    $types_to_skip = [
      'form',
      'html',
      'radio',
      'page',
      'entity_autocomplete',
      'datelist',
      'datetime',
      'button',
      'image_button',
      'file',
      'language_select',
      'machine_name',
      'password_confirm',
      'table',
      'tableselect',
    ];
    $types = array_diff(array_keys(\Drupal::service('plugin.manager.element_info')->getDefinitions()), $types_to_skip);
    sort($types);
    foreach ($types as $type) {
      $form['all_the_things'][$type] = [
        '#type' => $type,
      ];
      // @todo.
      if (in_array($type, ['checkboxes', 'radios', 'select'], TRUE)) {
        $form['all_the_things'][$type]['#options'] = [
          'foo' => 'Foo',
          'bar' => 'Bar',
        ];
      }
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Save configuration',
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
