<?php

namespace Drupal\user\Form;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Element\Email;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\Url;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a user preregister form.
 *
 * First step of registration process.
 *
 * @internal
 */
class PreregisterForm extends FormBase {

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
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

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
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   *   The typed data manager.
   * @param \Drupal\Component\Utility\EmailValidatorInterface $email_validator
   *   The email validator service.
   */
  public function __construct(UserStorageInterface $user_storage, LanguageManagerInterface $language_manager, ConfigFactory $config_factory, FloodInterface $flood, TypedDataManagerInterface $typed_data_manager, EmailValidatorInterface $email_validator) {
    $this->userStorage = $user_storage;
    $this->languageManager = $language_manager;
    $this->configFactory = $config_factory;
    $this->flood = $flood;
    $this->typedDataManager = $typed_data_manager;
    $this->emailValidator = $email_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('language_manager'),
      $container->get('config.factory'),
      $container->get('flood'),
      $container->get('typed_data_manager'),
      $container->get('email.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_preregister';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['infotext'] = [
      '#prefix' => '<p>',
      '#markup' => $this->t('Enter your mail address. After submit you will get a mail with the registration link.'),
      '#suffix' => '</p>',
    ];

    $form['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('email address'),
      '#size' => 60,
      '#maxlength' => Email::EMAIL_MAX_LENGTH,
      '#required' => TRUE,
      '#attributes' => [
        'autocorrect' => 'off',
        'autocapitalize' => 'off',
        'spellcheck' => 'false',
        'autofocus' => 'autofocus',
      ],
    ];

    $form['consent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Consent'),
      '#required' => TRUE,
      '#description' => 'Make text editable by backend or use new form display for preregister.',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    $form['#cache']['contexts'][] = 'url.query_args';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $flood_config = $this->configFactory->get('user.flood');
    if (!$this->flood->isAllowed('user.register', $flood_config->get('ip_limit'), $flood_config->get('ip_window'))) {
      $form_state->setErrorByName('name', $this->t('Too many requests from your IP address. It is temporarily blocked. Try again later or contact the site administrator.'));
      return;
    }
    $this->flood->register('user.register', $flood_config->get('ip_window'));

    $mail = trim($form_state->getValue('mail'));
    if (!$this->emailValidator->isValid($mail)) {
      $form_state->setErrorByName('mail', $this->t("The email address is invalid."));
      return;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $mail = trim($form_state->getValue('mail'));

    $data = [
      'mail' => $mail,
      'time' => \Drupal::time()->getRequestTime(),
    ];

    // Generate hash and token.
    $bytes = 16;
    $token = bin2hex(random_bytes($bytes));
    $hash = bin2hex(random_bytes($bytes));
    // @todo Encrypt data with hash before storing.
    $data = $data;

    // Store for 24h.
    \Drupal::service('keyvalue.expirable')->get('user')->setWithExpire($token, $data, 86400);

    // Generate URL for step 2.
    $url = Url::fromUri('internal:/user/register/' . $token . '/' . $hash);

    // @todo Send opt in mail.
    $this->messenger()
      ->addStatus($this->t('Proof of concept: Instead of sending a mail you was redirected to step 2.'));

    // @todo Do not redirect.
    $form_state->setRedirectUrl($url);
  }

}
