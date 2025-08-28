<?php

namespace Drupal\system\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\system\Form\SystemBrandingOffCanvasForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to display 'Site branding' elements.
 */
#[Block(
  id: "system_branding_block",
  admin_label: new TranslatableMarkup("Site branding"),
  forms: ['settings_tray' => SystemBrandingOffCanvasForm::class]
)]
class SystemBrandingBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Stores the configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Creates a SystemBrandingBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'use_site_logo' => TRUE,
      'use_site_name' => TRUE,
      'use_site_slogan' => TRUE,
      'label_display' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['block_branding'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Toggle branding elements'),
      '#description' => $this->t('Choose which branding elements you want to show in this block instance.'),
    ];
    $form['block_branding']['use_site_logo'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Site logo'),
      '#default_value' => $this->configuration['use_site_logo'],
    ];

    // Provide links to the theme settings pages if the user has access.
    $system_theme_settings_url = Url::fromRoute('system.theme_settings');
    $block_theme_settings_url = Url::fromRoute('system.theme_settings_theme', [
      'theme' => \Drupal::service('theme.manager')->getActiveTheme()->getName(),
    ]);
    if ($system_theme_settings_url->access() && $block_theme_settings_url->access()) {
      $form['block_branding']['use_site_logo']['#description'] = $this->t('Defined on the <a href=":appearance">Appearance Settings</a> or <a href=":theme">Theme Settings</a> page.', [
        ':appearance' => $system_theme_settings_url->toString(),
        ':theme' => $block_theme_settings_url->toString(),
      ]);
    }

    $form['block_branding']['use_site_name'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Site name'),
      '#default_value' => $this->configuration['use_site_name'],
    ];
    $form['block_branding']['use_site_slogan'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Site slogan'),
      '#default_value' => $this->configuration['use_site_slogan'],
    ];

    // Provide link to the site settings page if the user has access.
    $site_settings_url = Url::fromRoute('system.site_information_settings');
    if ($site_settings_url->access()) {
      $description = $this->t('Defined on the <a href=":site-settings">Site Information</a> page.', [
        ':site-settings' => $site_settings_url->toString(),
      ]);
      $form['block_branding']['use_site_name']['#description'] = $description;
      $form['block_branding']['use_site_slogan']['#description'] = $description;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $block_branding = $form_state->getValue('block_branding');
    $this->configuration['use_site_logo'] = $block_branding['use_site_logo'];
    $this->configuration['use_site_name'] = $block_branding['use_site_name'];
    $this->configuration['use_site_slogan'] = $block_branding['use_site_slogan'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $site_config = $this->configFactory->get('system.site');

    $build['site_logo'] = [
      '#theme' => 'image',
      '#uri' => theme_get_setting('logo.url'),
      '#alt' => $this->t('Home'),
      '#access' => $this->configuration['use_site_logo'],
    ];

    $build['site_name'] = [
      '#markup' => $site_config->get('name'),
      '#access' => $this->configuration['use_site_name'],
    ];

    $build['site_slogan'] = [
      '#markup' => $site_config->get('slogan'),
      '#access' => $this->configuration['use_site_slogan'],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(
      parent::getCacheTags(),
      $this->configFactory->get('system.site')->getCacheTags()
    );
  }

}
