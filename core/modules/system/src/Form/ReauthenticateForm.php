<?php

namespace Drupal\system\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\user\UserAuthenticationInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reauthenticate users before certain actions.
 *
 * @internal
 */
class ReauthenticateForm extends FormBase implements WorkspaceSafeFormInterface {

  /**
   * The timeout in seconds before reauthentication is requested again.
   *
   * @todo Make this configurable.
   */
  protected const REAUTHENTICATE_TIMEOUT = 3600;

  public function __construct(
    // @todo Currently this form depends on user.module.
    protected UserStorageInterface $userStorage,
    protected UserAuthenticationInterface $userAuth,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('user.auth')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'reauthenticate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = ['#markup' => $this->t('This operation requires you to enter your password.')];
    $form['pass'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#size' => 60,
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'current-password',
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Authenticate')];

    $form['#validate'][] = '::validateAuthentication';

    return $form;
  }

  /**
   * Checks supplied password.
   */
  public function validateAuthentication(array &$form, FormStateInterface $form_state) {
    $password = trim($form_state->getValue('pass'));
    $account = $this->userStorage->load($this->currentUser()->id());
    $isAuthenticated = $this->userAuth->authenticateAccount($account, $password);

    if (!$isAuthenticated) {
      // @todo Consider logging a notice here.
      $form_state->setError($form['pass'], $this->t('Unrecognized password.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $session = $this->getRequest()->getSession();
    $session->set('reauthenticate_timeout', time() + self::REAUTHENTICATE_TIMEOUT);
  }

}
