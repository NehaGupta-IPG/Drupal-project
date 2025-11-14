<?php

declare(strict_types=1);

namespace Drupal\form_test\Form;

use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form builder for testing hook_form_alter() and hook_form_FORM_ID_alter().
 *
 * @internal
 */
class FormTestFormIdEqualsBaseFormIdForm extends FormBase implements BaseFormIdInterface {

  public const FORM_ID = 'form_test_form_id_equals_base_form_id_form';

  public const BASE_FORM_ID = self::FORM_ID;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return self::FORM_ID;
  }

  public function getBaseFormId() {
    return self::BASE_FORM_ID;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Elements can be added as needed for future testing needs, but for now,
    // we're only testing alter hooks that do not require any elements added by
    // this function.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

}
