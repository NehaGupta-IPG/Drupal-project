<?php

namespace Drupal\system\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Url;
use Drupal\user\PermissionHandlerInterface;

/**
 * Configure maintenance settings for this site.
 *
 * @internal
 */
class SiteMaintenanceModeForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected StateInterface $state,
    protected PermissionHandlerInterface $permissionHandler,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'system_site_maintenance_mode';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['system.maintenance'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $permissions = $this->permissionHandler->getPermissions();
    $permission_label = $permissions['access site in maintenance mode']['title'];
    $form['maintenance_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Put site into maintenance mode'),
      '#default_value' => $this->state->get('system.maintenance_mode'),
      '#description' => $this->t('Visitors will only see the maintenance mode message. Only users with the "@permission-label" <a href=":permissions-url">permission</a> will be able to access the site. Authorized users can log in directly via the <a href=":user-login">user login</a> page.', [
        '@permission-label' => $permission_label,
        ':permissions-url' => Url::fromRoute('user.admin_permissions')
          ->toString(),
        ':user-login' => Url::fromRoute('user.login')->toString(),
      ]),
    ];
    $form['maintenance_mode_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to display when in maintenance mode'),
      '#config_target' => 'system.maintenance:message',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->state->set('system.maintenance_mode', $form_state->getValue('maintenance_mode'));
    parent::submitForm($form, $form_state);
  }

}
