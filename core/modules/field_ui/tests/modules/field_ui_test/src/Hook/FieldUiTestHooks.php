<?php

declare(strict_types=1);

namespace Drupal\field_ui_test\Hook;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for field_ui_test.
 */
class FieldUiTestHooks {

  /**
   * Implements hook_ENTITY_TYPE_access().
   */
  #[Hook('field_config_access')]
  public function fieldConfigAccess(FieldConfigInterface $field): AccessResultInterface {
    return AccessResult::forbiddenIf($field->getName() == 'highlander');
  }

  /**
   * Implements hook_form_FORM_BASE_ID_alter().
   */
  #[Hook('form_entity_view_display_edit_form_alter')]
  public function formEntityViewDisplayEditFormAlter(&$form, FormStateInterface $form_state) : void {
    $display = $form_state->getFormObject()->getEntity();
    $entity_test_display = $display->getTargetEntityTypeId() === 'entity_test';
    $test_field_parent = \Drupal::state()->get('field_ui_test.group_parent', '');
    $group_region = $entity_test_display ? \Drupal::state()->get('field_ui_test.group_region', 'content') : 'content';
    $table =& $form['fields'];
    foreach (Element::children($table) as $name) {
      $default_parent = $entity_test_display ? ($name === 'field_test_text' ? $test_field_parent : '') : 'indent';
      $table[$name]['parent_wrapper']['parent']['#options'] = ['indent' => 'Indent'];
      $table[$name]['parent_wrapper']['parent']['#default_value'] = $default_parent;
    }
    $table['indent'] = [
      '#attributes' => [
        'class' => [
          'draggable',
          'field-group',
        ],
        'id' => 'indent-id',
      ],
      '#row_type' => 'group',
      '#region_callback' => [$this, 'regionCallback'],
      '#test_region' => $group_region,
      '#js_settings' => [
        'rowHandler' => 'group',
      ],
      'human_name' => [
        '#markup' => 'Indent',
        '#prefix' => '<span class="group-label">',
        '#suffix' => '</span>',
      ],
      'machine_name' => [
        '#markup' => 'indent',
        '#attributes' => ['class' => ['machine-name']],
      ],
      'weight' => [
        '#type' => 'textfield',
        '#default_value' => -100,
        '#size' => 3,
        '#attributes' => [
          'class' => [
            'field-weight',
          ],
        ],
      ],
      'parent_wrapper' => [
        'parent' => [
          '#type' => 'select',
          '#options' => [
            'indent' => 'Indent',
          ],
          '#empty_value' => '',
          '#default_value' => '',
          '#attributes' => [
            'class' => [
              'field-parent',
            ],
          ],
          '#parents' => [
            'fields',
            'indent',
            'parent',
          ],
        ],
        'hidden_name' => [
          '#type' => 'hidden',
          '#default_value' => 'indent',
          '#attributes' => [
            'class' => [
              'field-name',
            ],
          ],
        ],
      ],
      'region' => [
        '#type' => 'select',
        '#title' => 'Region for Indent',
        '#title_display' => 'invisible',
        '#options' => [
          'content' => 'Content',
          'hidden' => 'Disabled',
        ],
        '#default_value' => $group_region,
        '#attributes' => ['class' => ['field-region']],
        '#parents' => [
          'fields',
          'indent',
          'region',
        ],
      ],
    ];
    $form['#attached']['library'][] = 'field_ui_test/display_group';
  }

  /**
   * Returns the region for a display form row.
   *
   * @param array $row
   *   The render array for the row.
   *
   * @return string
   *   The region name.
   */
  public function regionCallback($row): string {
    return $row['#test_region'];
  }

  /**
   * Implements hook_field_formatter_third_party_settings_form().
   *
   * We add an empty hook implementation to test it does not result in a fatal
   * error in \Drupal\field_ui\Form\EntityViewDisplayEditForm::thirdPartySettingsForm.
   *
   * @see \Drupal\field_ui\Form\EntityViewDisplayEditForm::thirdPartySettingsForm
   * @see \Drupal\Tests\field_ui\Functional\FieldUIIndentationTest::testIndentation
   *
   * @link https://www.drupal.org/project/drupal/issues/3552531
   */
  #[Hook('field_formatter_third_party_settings_form')]
  public function fieldFormatterThirdPartySettingsForm(): void {
    // Empty hook implementation.
  }

}
