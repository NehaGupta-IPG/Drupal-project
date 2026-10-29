<?php

namespace Drupal\Core\Installer\Form;

use Drupal\Core\Datetime\TimeZoneFormHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\user\UserInterface;
use Drupal\user\UserNameValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Site\SettingsEditor;

/**
 * Provides the site configuration form.
 *
 * @internal
 */
class SiteConfigureForm extends ConfigFormBase {

  /**
   * Constructs a new SiteConfigureForm.
   *
   * @param string $root
   *   The app root.
   * @param string $sitePath
   *   The site path.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller
   *   The module installer.
   * @param \Drupal\user\UserNameValidator $userNameValidator
   *   The user validator.
   * @param bool $superUserAccessPolicy
   *   The value of the 'security.enable_super_user' container parameter.
   */
  public function __construct(
    protected $root,
    protected $sitePath,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleInstallerInterface $moduleInstaller,
    protected UserNameValidator $userNameValidator,
    protected bool $superUserAccessPolicy,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->getParameter('app.root'),
      $container->getParameter('site.path'),
      $container->get('entity_type.manager'),
      $container->get('module_installer'),
      $container->get('user.name_validator'),
      // In order to disable the super user policy this must be set to FALSE. If
      // the container parameter is missing then the policy is enabled. See
      // \Drupal\Core\DependencyInjection\Compiler\SuperUserAccessPolicyPass.
      $container->getParameter('security.enable_super_user') ?? TRUE,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'install_configure_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'system.date',
      'system.site',
      'update.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    global $install_state;
    $form['#title'] = $this->t('Configure site');

    // Warn about settings.php permissions risk.
    $settings_dir = $this->sitePath;
    $settings_file = $settings_dir . '/settings.php';
    // Check that $_POST is empty so we only show this message when the form is
    // first displayed, not on the next page after it is submitted. (We do not
    // want to repeat it multiple times because it is a general warning that is
    // not related to the rest of the installation process; it would also be
    // especially out of place on the last page of the installer, where it would
    // distract from the message that the Drupal installation has completed
    // successfully.)
    $post_params = $this->getRequest()->request->all();
    if (empty($post_params) && (Settings::get('skip_permissions_hardening') || !drupal_verify_install_file($this->root . '/' . $settings_file, FILE_EXIST | FILE_READABLE | FILE_NOT_WRITABLE) || !drupal_verify_install_file($this->root . '/' . $settings_dir, FILE_NOT_WRITABLE, 'dir'))) {
      $this->messenger()
        ->addWarning($this->t('All necessary changes to %dir and %file have been made, so you should remove write permissions to them now in order to avoid security risks. If you are unsure how to do so, consult the <a href=":handbook_url">online handbook</a>.', [
          '%dir' => $settings_dir,
          '%file' => $settings_file,
          ':handbook_url' => 'https://www.drupal.org/server-permissions',
        ]));
    }

    $form['#attached']['library'][] = 'core/drupal.fieldgroup';
    $form['#attached']['library'][] = 'system/drupal.system';
    // Add JavaScript time zone detection.
    $form['#attached']['library'][] = 'core/drupal.timezone';
    // We add these strings as settings because JavaScript translation does not
    // work during installation.
    $form['#attached']['drupalSettings']['copyFieldValue']['edit-site-mail'] = ['edit-account-mail'];

    $form['site_information'] = [
      '#type' => 'fieldset',
      '#attributes' => ['class' => ['fieldgroup']],
      '#title' => $this->t('Site information'),
      '#access' => empty($install_state['config_install_path']),
    ];
    $form['site_information']['site_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site name'),
      '#required' => TRUE,
      '#weight' => -20,
      '#access' => empty($install_state['config_install_path']),
    ];
    $form['site_information']['site_mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Site email address'),
      '#default_value' => $this->config('system.site')->get('mail'),
      '#description' => $this->t("Automated emails, such as registration information, will be sent from this address. Use an address ending in your site's domain to help prevent these emails from being flagged as spam."),
      '#required' => TRUE,
      '#weight' => -15,
      '#access' => empty($install_state['config_install_path']),
    ];

    if (count($this->getAdminRoles()) === 0 && $this->superUserAccessPolicy === FALSE) {
      $account_label = $this->t('Site account');
    }
    else {
      $account_label = $this->t('Site maintenance account');
    }

    $form['admin_account'] = [
      '#type' => 'fieldset',
      '#attributes' => ['class' => ['fieldgroup']],
      '#title' => $account_label,
    ];
    $form['admin_account']['account']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#maxlength' => UserInterface::USERNAME_MAX_LENGTH,
      '#description' => $this->t("Several special characters are allowed, including space, period (.), hyphen (-), apostrophe ('), underscore (_), and the @ sign."),
      '#required' => TRUE,
      '#attributes' => ['class' => ['username']],
    ];
    $form['admin_account']['account']['pass'] = [
      '#type' => 'password_confirm',
      '#required' => TRUE,
      '#size' => 25,
    ];
    $form['admin_account']['account']['#tree'] = TRUE;
    $form['admin_account']['account']['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#required' => TRUE,
    ];

    $form['trusted_host_patterns'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Trusted hosting patterns'),
    ];
    $form['trusted_host_patterns']['trusted_host_patterns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Trusted website domains'),
      '#placeholder' => $this->t("example.com\nmy-domain.com\nlocalhost"),
      '#description' => $this->t("To enable Drupal's trusted hosts system add wanted domains one item per line. Don't use the caret (^), dollar sign ($), backslash (\) characters."),
      '#required' => FALSE,
      '#weight' => -30,
    ];

    $form['regional_settings'] = [
      '#type' => 'fieldset',
      '#attributes' => ['class' => ['fieldgroup']],
      '#title' => $this->t('Regional settings'),
      '#access' => empty($install_state['config_install_path']),
    ];
    // Use the default site timezone if one is already configured, or fall back
    // to the system timezone if set (and avoid throwing a warning in
    // PHP >=5.4).
    $default_timezone = $this->config('system.date')->get('timezone.default') ?: @date_default_timezone_get();
    $form['regional_settings']['date_default_timezone'] = [
      '#type' => 'select',
      '#title' => $this->t('Default time zone'),
      '#default_value' => $default_timezone,
      '#options' => TimeZoneFormHelper::getOptionsListByRegion(),
      '#weight' => 5,
      '#attributes' => ['class' => ['timezone-detect']],
      '#access' => empty($install_state['config_install_path']),
    ];

    $form['update_notifications'] = [
      '#type' => 'fieldset',
      '#attributes' => ['class' => ['fieldgroup']],
      '#title' => $this->t('Update notifications'),
      '#description' => $this->t('When checking for updates, your site automatically sends anonymous information to Drupal.org. See the <a href="@update-status-module-docs" target="_blank">Update Status module documentation</a> for details.', ['@update-status-module-docs' => 'https://www.drupal.org/node/178772']),
      '#access' => empty($install_state['config_install_path']),
    ];
    $form['update_notifications']['enable_update_status_module'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check for updates automatically'),
      '#default_value' => 1,
      '#access' => empty($install_state['config_install_path']),
    ];
    $form['update_notifications']['enable_update_status_emails'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Receive email notifications'),
      '#default_value' => 1,
      '#states' => [
        'visible' => [
          'input[name="enable_update_status_module"]' => ['checked' => TRUE],
        ],
      ],
      '#access' => empty($install_state['config_install_path']),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and continue'),
      '#weight' => 15,
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $violations = $this->userNameValidator->validateName($form_state->getValue(['account', 'name']));
    if ($violations->count() > 0) {
      $form_state->setErrorByName('account][name', $violations[0]->getMessage());
    }

    // If the textarea is empty don't validate the input string.
    $trusted_host_patterns_input = $form_state->getValue('trusted_host_patterns');
    if (!empty($trusted_host_patterns_input)) {
      // Creates an array of domain strings.
      $exploded_trusted_host_patterns = explode("\r\n", $trusted_host_patterns_input);
      foreach ($exploded_trusted_host_patterns as $exploded_trusted_host_patterns_items) {
        // It may be best to use the namespace below with the validate() method.
        // \Symfony\Component\Validator\Constraints\HostnameValidator
        // If the domain is invalid it will return an error.
        if (filter_var($exploded_trusted_host_patterns_items, \FILTER_VALIDATE_DOMAIN, \FILTER_FLAG_HOSTNAME) === FALSE) {
          $form_state->setErrorByName('trusted_host_patterns', $this->t("The @trusted_host_pattern domain is not recognized as a correct format", ['@trusted_host_pattern' => $exploded_trusted_host_patterns_items]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    global $install_state;

    if (empty($install_state['config_install_path'])) {
      $this->config('system.site')
        ->set('name', (string) $form_state->getValue('site_name'))
        ->set('mail', (string) $form_state->getValue('site_mail'))
        ->save();

      $this->config('system.date')
        ->set('timezone.default', (string) $form_state->getValue('date_default_timezone'))
        ->save();
    }

    $account_values = $form_state->getValue('account');

    // Enable update.module if this option was selected.
    $update_status_module = $form_state->getValue('enable_update_status_module');
    if (empty($install_state['config_install_path']) && $update_status_module) {
      $this->moduleInstaller->install(['update']);
      // After a module is installed, there is a new container, so all class
      // properties dependent on the container need to be reset.
      $this->resetPropertiesFromContainer();

      // Add the site maintenance account's email address to the list of
      // addresses to be notified when updates are available, if selected.
      $email_update_status_emails = $form_state->getValue('enable_update_status_emails');
      if ($email_update_status_emails) {
        // Reset the configuration so it is updated with the new module.
        $this->config('update.settings')->set('notification.emails', [$account_values['mail']])->save();
      }
    }

    // If the textarea is empty don't validate the input string.
    $trusted_host_patterns_input = $form_state->getValue('trusted_host_patterns');
    if (!empty($trusted_host_patterns_input)) {
      // Trusted host patterns array is used to write data to settings.php.
      $trusted_host_patterns_array = [];
      // Creates an array of trusted host patterns from input strings.
      $exploded_trusted_host_patterns = explode("\r\n", $trusted_host_patterns_input);
      foreach ($exploded_trusted_host_patterns as $exploded_trusted_host_patterns_items) {
        // It may be best to use the namespace below with the validate() method.
        // \Symfony\Component\Validator\Constraints\HostnameValidator.
        if (filter_var($exploded_trusted_host_patterns_items, \FILTER_VALIDATE_DOMAIN, \FILTER_FLAG_HOSTNAME) === FALSE) {
          $form_state->setErrorByName('trusted_host_patterns', $this->t("The @trusted_host_pattern domain is not recognized as a correct format", ['@trusted_host_pattern' => $exploded_trusted_host_patterns_items]));
          // Reset trusted host pattern array to empty this stops
          // the array contents being written into the settings.php file.
          array_diff($trusted_host_patterns_array, $trusted_host_patterns_array);
          break;
        }
        // Replace all full stops with a forward slash and full stop.
        $trusted_host_pattern_item = str_replace(".", "\.", $exploded_trusted_host_patterns_items);
        // Add trusted_host_patterns to array.
        $trusted_host_patterns_array[] = '^' . $trusted_host_pattern_item . '$';
      }

      // If trusted host patterns array is not empty then write to settings.php.
      if (!empty($trusted_host_patterns_array)) {
        $settings['settings']['trusted_host_patterns'] = (object) [
          'value' => $trusted_host_patterns_array,
          'required' => TRUE,
        ];
        $settings_path = $this->sitePath . '/settings.php';
        chmod($settings_path, 0744);
        SettingsEditor::rewrite($settings_path, $settings);
        chmod($settings_path, 0444);
      }
    }

    // We created user 1 with placeholder values. Let's save the real values.
    /** @var \Drupal\user\UserInterface $account */
    $account = $this->entityTypeManager->getStorage('user')->load(1);
    $account->init = $account->mail = $account_values['mail'];
    $account->roles = $account->getRoles();
    $account->activate();
    $account->timezone = $form_state->getValue('date_default_timezone');
    $account->pass = $account_values['pass'];
    $account->name = $account_values['name'];

    // Ensure user 1 has an administrator role if one exists.
    /** @var \Drupal\user\RoleInterface[] $admin_roles */
    $admin_roles = $this->getAdminRoles();
    if (count(array_intersect($account->getRoles(), array_keys($admin_roles))) === 0) {
      if (count($admin_roles) > 0) {
        foreach ($admin_roles as $role) {
          $account->addRole($role->id());
        }
      }
      elseif ($this->superUserAccessPolicy === FALSE) {
        $this->messenger()->addWarning($this->t(
          'The user %username does not have administrator access. For more information, see the documentation on <a href="@secure-user-1-docs">securing the admin super user</a>.',
          [
            '%username' => $account->getDisplayName(),
            '@secure-user-1-docs' => 'https://www.drupal.org/docs/administering-a-drupal-site/security-in-drupal/securing-the-admin-super-user-1#s-disable-the-super-user-access-policy',
          ]
        ));
      }
    }

    $account->save();
  }

  /**
   * Returns the list of admin roles.
   *
   * @return \Drupal\user\RoleInterface[]
   *   The list of admin roles.
   */
  protected function getAdminRoles(): array {
    return $this->entityTypeManager->getStorage('user_role')->loadByProperties(['is_admin' => TRUE]);
  }

  /**
   * Repopulate class properties from container.
   */
  protected function resetPropertiesFromContainer(): void {
    $this->resetConfigFactory();
    $container = \Drupal::getContainer();
    $this->root = $container->getParameter('app.root');
    $this->sitePath = $container->getParameter('site.path');
    $this->entityTypeManager = $container->get('entity_type.manager');
    $this->moduleInstaller = $container->get('module_installer');
    $this->userNameValidator = $container->get('user.name_validator');
    $this->superUserAccessPolicy = $container->getParameter('security.enable_super_user') ?? TRUE;
  }

}
