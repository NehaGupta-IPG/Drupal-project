<?php

declare(strict_types=1);

namespace Drupal\ajax_forms_test\Controller;

use Drupal\ajax_forms_test\Form\AjaxFormsTestSingularForm;
use Drupal\Core\Controller\ControllerBase;

/**
 * Test class to render multiple ajax forms.
 */
class DuplicateForms extends ControllerBase {

  /**
   * Build multiple forms of the same type.
   *
   * @see \Drupal\ajax_forms_test\Form\AjaxFormsTestSingularForm
   */
  public function makeDuplicateForms(): array {
    $form_1 = $this->formBuilder()->getForm(new AjaxFormsTestSingularForm());
    $form_2 = $this->formBuilder()->getForm(new AjaxFormsTestSingularForm());

    return [
      $form_1,
      $form_2,
    ];
  }

}
