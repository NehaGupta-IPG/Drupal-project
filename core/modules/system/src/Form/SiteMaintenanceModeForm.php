<?php

namespace Drupal\system\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure maintenance settings for this site.
 *
 * @internal
 */
class SiteMaintenanceModeForm extends ConfigFormBase {

  /**
   * Constructs a new SiteMaintenanceModeForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state keyvalue collection to use.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected StateInterface $state,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('state'),
    );
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
    $form['maintenance_mode_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to display when in maintenance mode'),
      '#config_target' => 'system.maintenance:message',
    ];

    $form['maintenance_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Put site into maintenance mode'),
      '#default_value' => $this->state->get('system.maintenance_mode'),
      '#description' => $this->t('The maintenance mode message will not show on already cached pages without clearing the page cache.'),
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
