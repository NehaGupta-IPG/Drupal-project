<?php

namespace Drupal\contact\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\User;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;


/**
 * Contact form hooks.
 */
class ContactFormHooks {
    use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter() for \Drupal\user\ProfileForm.
   *
   * Add the enable personal contact form to an individual user's account page.
   *
   * @see \Drupal\user\ProfileForm::form()
   */
  #[Hook('form_user_form_alter')]
  public function formUserFormAlter(&$form, FormStateInterface $form_state) : void {
    $form['contact'] = [
      '#type' => 'details',
      '#title' => $this->t('Contact settings'),
      '#open' => TRUE,
      '#weight' => 5,
    ];
    $account = $form_state->getFormObject()->getEntity();
    if (!\Drupal::currentUser()->isAnonymous() && $account->id()) {
      $account_data = \Drupal::service('user.data')->get('contact', $account->id(), 'enabled');
    }
    $form['contact']['contact'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Personal contact form'),
      '#default_value' => $account_data ?? \Drupal::config('contact.settings')->get('user_default_enabled'),
      '#description' => $this->t('Allow other users to contact you via a personal contact form which keeps your email address hidden. Note that some privileged users such as site administrators are still able to contact you even if you choose to disable this feature.'),
    ];
    $form['actions']['submit']['#submit'][] = 'contact_user_profile_form_submit';
  }

  /**
   * Implements hook_form_FORM_ID_alter() for \Drupal\user\AccountSettingsForm.
   *
   * Adds the default personal contact setting on the user settings page.
   */
  #[Hook('form_user_admin_settings_alter')]
  public function formUserAdminSettingsAlter(&$form, FormStateInterface $form_state) : void {
    $form['contact'] = [
      '#type' => 'details',
      '#title' => $this->t('Contact settings'),
      '#open' => TRUE,
      '#weight' => 0,
    ];
    $form['contact']['contact_default_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable the personal contact form by default for new users'),
      '#description' => $this->t('Changing this setting will not affect existing users.'),
      '#default_value' => \Drupal::configFactory()->getEditable('contact.settings')->get('user_default_enabled'),
    ];
    // Add submit handler to save contact configuration.
    $form['#submit'][] = 'contact_form_user_admin_settings_submit';
  }

  /**
 * Form submission handler for \Drupal\user\ProfileForm.
 *
 * Saves the contact page setting.
 */
function contact_user_profile_form_submit($form, FormStateInterface $form_state): void {
    $account = $form_state->getFormObject()->getEntity();
    if ($account->id() && $form_state->hasValue('contact')) {
      \Drupal::service('user.data')->set('contact', $account->id(), 'enabled', (int) $form_state->getValue('contact'));
    }
  }
  
  /**
   * Form submission handler for \Drupal\user\AccountSettingsForm.
   *
   * @see contact_form_user_admin_settings_alter()
   */
  function contact_form_user_admin_settings_submit($form, FormStateInterface $form_state): void {
    \Drupal::configFactory()->getEditable('contact.settings')
      ->set('user_default_enabled', $form_state->getValue('contact_default_status'))
      ->save();
  }

}