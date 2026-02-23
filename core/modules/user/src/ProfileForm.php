<?php

namespace Drupal\user;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for the profile forms.
 *
 * @internal
 */
class ProfileForm extends AccountForm {

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $element = parent::actions($form, $form_state);

    if (!$this->entity->isNew() && $this->entity->hasLinkTemplate('cancel-form')) {
      $route_info = $this->entity->toUrl('cancel-form');
      if ($this->getRequest()->query->has('destination')) {
        $query = $route_info->getOption('query');
        $query['destination'] = $this->getRequest()->query->get('destination');
        $route_info->setOption('query', $query);
      }
      $element['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel account'),
        '#access' => $this->entity->id() > 1 && $this->entity->access('delete'),
        '#attributes' => [
          'class' => ['button', 'button--danger'],
        ],
      ];
      $element['delete']['#url'] = $route_info;
    }

    // Determine the user approval method.
    $user_register = \Drupal::configFactory()->get('user.settings')->get('register');
    switch ($user_register) {
      case UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL:
        // Only add 'Send awaiting approval' button when user settings allow
        // visitors creating accounts with required administrator approval.
        $send_awaiting_approval = TRUE;
        break;

      default:
        $send_awaiting_approval = FALSE;
    }

    if (!$this->entity->isActive() && !$send_awaiting_approval) {
      return $element;
    }

    $element['send']['#type'] = 'submit';
    $element['send']['#value'] = $this->entity->isActive() ? $this->t('Send welcome message') : $this->t('Send awaiting approval message');
    $element['send']['#submit'] = ['::editSendSubmit'];
    $element['send']['#access'] = $this->entity->getEmail() && $this->currentUser()->hasPermission('administer users');

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $account = $this->entity;
    $account->save();
    $form_state->setValue('uid', $account->id());

    $this->messenger()->addStatus($this->t('The changes have been saved.'));
  }

  /**
   * Provides a submit handler for the 'Send welcome message' button.
   */
  public function editSendSubmit(array $form, FormStateInterface $form_state): void {
    $account = $this->entity;

    if (!$account->isActive()) {
      $op = 'register_pending_approval';
    }
    else {
      // Determine the user approval method.
      switch (\Drupal::config('user.settings')->get('register')) {
        case UserInterface::REGISTER_ADMINISTRATORS_ONLY:
          $op = 'register_admin_created';
          break;

        case UserInterface::REGISTER_VISITORS:
        default:
          $op = 'register_no_approval_required';
      }
    }

    // Notify the user via email.
    $mail = _user_mail_notify($op, $account);

    // Log the mail.
    if (!empty($mail)) {
      $this->getLogger('user')
        ->notice('Welcome message has been sent to %name at %email.', [
          '%name' => $account->getAccountName(),
          '%email' => $account->getEmail(),
        ]);
      $this->messenger()
        ->addMessage($this->t('Welcome message has been sent to %name at %email', [
          '%name' => $account->getAccountName(),
          '%email' => $account->getEmail(),
        ]));
    }
    else {
      $this->getLogger('user')
        ->notice('There was an error sending the welcome message to %name at %email', [
          '%name' => $account->getAccountName(),
          '%email' => $account->getEmail(),
        ]);
      $this->messenger()
        ->addMessage($this->t('There was an error sending the welcome message to %name at %email', [
          '%name' => $account->getAccountName(),
          '%email' => $account->getEmail(),
        ]), 'error');
    }
  }

}
