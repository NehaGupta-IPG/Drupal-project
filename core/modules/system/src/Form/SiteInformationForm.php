<?php

namespace Drupal\system\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure site information settings for this site.
 *
 * @internal
 */
class SiteInformationForm extends ConfigFormBase {

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * Constructs a SiteInformationForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Routing\RequestContext $request_context
   *   The request context.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typedConfigManager, AliasManagerInterface $alias_manager, RequestContext $request_context) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->aliasManager = $alias_manager;
    $this->requestContext = $request_context;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('path_alias.manager'),
      $container->get('router.request_context')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'system_site_information_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['system.site'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $site_config = $this->config('system.site');

    $form['site_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Site details'),
      '#open' => TRUE,
    ];
    $form['site_information']['site_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site name'),
      '#config_target' => 'system.site:name',
      '#required' => TRUE,
    ];
    $form['site_information']['site_slogan'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Slogan'),
      '#config_target' => 'system.site:slogan',
      '#description' => $this->t("How this is used depends on your site's theme."),
      '#maxlength' => 255,
    ];
    $form['site_information']['site_mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#config_target' => new ConfigTarget(
        'system.site',
        'mail',
        fromConfig: fn($value) => $value ?: ini_get('sendmail_from'),
      ),
      '#description' => $this->t("The <em>From</em> address in automated emails sent during registration and new password requests, and other notifications. (Use an address ending in your site's domain to help prevent this email being flagged as spam.)"),
      '#required' => TRUE,
    ];
    $form['front_page'] = [
      '#type' => 'details',
      '#title' => $this->t('Front page'),
      '#open' => TRUE,
    ];
    $form['front_page']['site_frontpage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default front page'),
      '#config_target' => new ConfigTarget(
        'system.site',
        'page.front',
        fromConfig: fn($value) => $this->aliasManager->getPathByAlias($value),
        toConfig: fn($value) => $this->aliasManager->getPathByAlias($value),
      ),
      '#required' => TRUE,
      '#size' => 40,
      '#description' => $this->t('Specify a relative URL to display as the front page.'),
      '#field_prefix' => $this->requestContext->getCompleteBaseUrl(),
    ];
    $form['error_page'] = [
      '#type' => 'details',
      '#title' => $this->t('Error pages'),
      '#open' => TRUE,
    ];
    $form['error_page']['site_403'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default 403 (access denied) page'),
      '#config_target' => new ConfigTarget(
        'system.site',
        'page.403',
        fromConfig: fn($value) => $this->aliasManager->getPathByAlias($value),
        toConfig: fn($value) => $this->aliasManager->getPathByAlias($value),
      ),
      '#size' => 40,
      '#description' => $this->t('This page is displayed when the requested document is denied to the current user. Leave blank to display a generic "access denied" page.'),
    ];
    $form['error_page']['site_404'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default 404 (not found) page'),
      '#config_target' => new ConfigTarget(
        'system.site',
        'page.404',
        fromConfig: fn($value) => $this->aliasManager->getPathByAlias($value),
        toConfig: fn($value) => $this->aliasManager->getPathByAlias($value),
      ),
      '#size' => 40,
      '#description' => $this->t('This page is displayed when no other content matches the requested document. Leave blank to display a generic "page not found" page.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('system.site')
      ->set('page.front', $form_state->getValue('site_frontpage'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
