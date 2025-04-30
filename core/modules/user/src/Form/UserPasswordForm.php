<?php

namespace Drupal\user\Form;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Element\Email;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Drupal\user\UserNameValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;

/**
 * Provides a user password reset form.
 *
 * Send the user an email to reset their password.
 *
 * @internal
 */
class UserPasswordForm extends FormBase implements WorkspaceSafeFormInterface {

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The flood service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * The email validator service.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  protected $emailValidator;

  /**
   * Constructs a UserPasswordForm object.
   *
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service.
   * @param \Drupal\user\UserNameValidator $userNameValidator
   *   The user validator service.
   * @param \Drupal\Component\Utility\EmailValidatorInterface $email_validator
   *   The email validator service.
   */
  public function __construct(
    UserStorageInterface $user_storage,
    LanguageManagerInterface $language_manager,
    ConfigFactory $config_factory,
    FloodInterface $flood,
    protected UserNameValidator $userNameValidator,
    EmailValidatorInterface $email_validator,
  ) {
    $this->userStorage = $user_storage;
    $this->languageManager = $language_manager;
    $this->configFactory = $config_factory;
    $this->flood = $flood;
    $this->emailValidator = $email_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('language_manager'),
      $container->get('config.factory'),
      $container->get('flood'),
      $container->get('user.name_validator'),
      $container->get('email.validator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_pass';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // When a user requests a password reset we check for username and email
    // conflicts using a multistep form.
    $step_value = $form_state->getValue('step');
    if (empty($step_value)) {
      $form['step'] = [
        '#type' => 'hidden',
        '#value' => 1,
      ];
      $form_state->setValue('step', 1);
    }
    if ($form_state->getValue('step') == 1) {
      $form['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Username or email address'),
        '#size' => 60,
        '#maxlength' => max(UserInterface::USERNAME_MAX_LENGTH,
          Email::EMAIL_MAX_LENGTH),
        '#required' => TRUE,
        '#attributes' => [
          'autocorrect' => 'off',
          'autocapitalize' => 'off',
          'spellcheck' => 'false',
          'autofocus' => 'autofocus',
        ],
      ];
      // Allow logged in users to request this also.
      $user = $this->currentUser();
      if ($user->isAuthenticated()) {
        $form['name']['#type'] = 'value';
        $form['name']['#value'] = $user->getEmail();
        $form['mail'] = [
          '#prefix' => '<p>',
          '#markup' => $this->t('Password reset instructions will be mailed to %email. You must log out to use the password reset link in the e-mail.', ['%email' => $user->getEmail()]),
          '#suffix' => '</p>',
        ];
      }
      $form['name']['#default_value'] = $this->getRequest()->query->get('name');
    }
    else {
      // Where there is a conflict between the
      // username and email address for two users
      // we supply both accounts as an option for
      // the password reset.
      $accounts = $form_state->getStorage()['accounts'];
      $options = [];
      foreach ($accounts as $account) {
        $label = $this->t('The account with the username: @name', ['@name' => $account->getUsername()]);
        if ($account->getEmail() === $form_state->getStorage()['name']) {
          $label = $this->t('The account with the email address: @email', ['@email' => $account->getEmail()]);
        }
        $options[Crypt::hashBase64(Settings::getHashSalt() . $account->id())] = $label;
      }
      $form['choose_account'] = [
        '#type' => 'radios',
        '#title' => $this->t('Choose account'),
        '#required' => TRUE,
        '#prefix' => "<p>" . $this->t("There is a username conflict with the email address @email. Select which account password to reset.", ['@email' => $form_state->getStorage()['name']]) . "</p>",
        '#options' => $options,
        '#default_value' => Crypt::hashBase64(Settings::getHashSalt() . reset($accounts)->id()),
      ];

      $form['step'] = [
        '#type' => 'hidden',
        '#value' => 2,
      ];
    }
    $form['actions'] = ['#type' => 'actions'];
    if ($form_state->getValue('step') == 2) {
      $form['actions']['cancel'] = [
        '#type' => 'submit',
        '#value' => $this->t('Cancel'),
        '#name' => 'cancel',
        '#limit_validation_errors' => [],
        '#weight' => 5,
      ];
    }
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#name' => 'submit',
    ];
    $form['#cache']['contexts'][] = 'url.query_args';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('step') == 1) {
      $name = trim($form_state->getValue('name'));
      $accounts = [];
      // Try to load by email.
      $users = $this->userStorage->loadByProperties(['mail' => $name]);
      $account_by_email = reset($users);
      if ($account_by_email) {
        $accounts[Crypt::hashBase64(Settings::getHashSalt() . $account_by_email->id())] = $account_by_email;
      }
      // Also try to load by user name, but only when the user is not logged in.
      $user = $this->currentUser();
      if ($user->id() == 0) {
        $users = $this->userStorage->loadByProperties(['name' => $name]);
        $account_by_name = reset($users);
        if ($account_by_name) {
          $accounts[Crypt::hashBase64(Settings::getHashSalt() . $account_by_name->id())] = $account_by_name;
        }
      }
      if (!empty($accounts)) {
        if (count($accounts) == 1) {
          $account = reset($accounts);
          // Blocked accounts cannot request a new password.
          if (!$account->isActive()) {
            $form_state->setErrorByName('name', $this->t('%name is blocked or has not been activated yet.', ['%name' => $account->getUsername()]));
          }
        }
        $form_state->setValue('accounts', $accounts);
      }
      else {
        $form_state->setErrorByName('name', $this->t('Sorry, %name is not recognized as a username or an email address.', ['%name' => $name]));
      }
    }
    elseif ($form_state->getValue('step') == 2) {
      $chosen_account = $form_state->getValue('choose_account');
      $account = $form_state->getStorage()['accounts'][$chosen_account];
      // Blocked accounts cannot request a new password.
      if (!$account->isActive()) {
        $form_state->setErrorByName('choose_account', $this->t('%name is blocked or has not been activated yet.', ['%name' => $account->getUsername()]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    if ($form_state->getValue('step') == 1) {
      $accounts = $form_state->getValue('accounts');
      if (count($accounts) > 1) {
        $form_state->setValue('step', 2);
        $form_state->setStorage([
          'name' => $form_state->getValue('name'),
          'accounts' => $accounts,
        ]);
        $form_state->setRebuild();
      }

      else {
        $account = reset($accounts);
      }
    }

    else {
      if ($form_state->getTriggeringElement()['#name'] == 'submit') {
        $chosen_account = $form_state->getValue('choose_account');
        $account = $form_state->getStorage()['accounts'][$chosen_account];
      }
      else {
        $form_state->setRedirectUrl(Url::fromRoute('user.pass'));
      }
    }
    if (isset($account)) {
      // Mail one-time login URL and instructions using current language.
      $mail = _user_mail_notify('password_reset', $account);
      if (!empty($mail)) {
        $this->logger('user')->notice('Password reset instructions mailed to %name at %email.', [
          '
        %name' => $account->name,
          '%email' => $account->mail,
        ]);
        $this->messenger($this->t('Further instructions have been sent to your email address.'));
      }

      $form_state->setRedirectUrl(Url::fromRoute('user.page'));
    }
  }

}
