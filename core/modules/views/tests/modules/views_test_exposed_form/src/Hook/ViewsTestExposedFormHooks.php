<?php

declare(strict_types=1);

namespace Drupal\views_test_exposed_form\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for views_test_exposed_form.
 */
class ViewsTestExposedFormHooks {

  /**
   * Implements hook_form_FORM_ID_alter() for the exposed form.
   */
  #[Hook('form_views_exposed_form_alter')]
  public function formViewsExposedFormAlter(&$form, FormStateInterface $form_state) : void {
    if ($form_state->get('view')->id() === 'test_page_with_ajax_exposed_form_in_block') {
      $form['form_build_time'] = [
        '#type' => 'item',
        '#markup' => \Drupal::time()->getCurrentMicroTime(),
      ];
    }
  }

}