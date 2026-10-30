<?php

namespace Drupal\language;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for language negotiation methods.
 */
abstract class LanguageNegotiationMethodBase implements LanguageNegotiationMethodInterface, ContainerFactoryPluginInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The current active user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static();
    $instance->config = $container->get('config.factory');
    $instance->currentUser = $container->get('current_user');
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function setLanguageManager(ConfigurableLanguageManagerInterface $language_manager) {
    @trigger_error(__METHOD__ . ' is deprecated in drupal:10.1.0 and is removed in drupal:11.0.0. Use Drupal\Core\Plugin\ContainerFactoryPluginInterface instead. See https://www.drupal.org/node/2712789', E_USER_DEPRECATED);
  }

  /**
   * {@inheritdoc}
   */
  public function setConfig(ConfigFactoryInterface $config) {
    @trigger_error(__METHOD__ . ' is deprecated in drupal:10.1.0 and is removed in drupal:11.0.0. Use Drupal\Core\Plugin\ContainerFactoryPluginInterface instead. See https://www.drupal.org/node/2712789', E_USER_DEPRECATED);
  }

  /**
   * {@inheritdoc}
   */
  public function setCurrentUser(AccountInterface $current_user) {
    @trigger_error(__METHOD__ . ' is deprecated in drupal:10.1.0 and is removed in drupal:11.0.0. Use Drupal\Core\Plugin\ContainerFactoryPluginInterface instead. See https://www.drupal.org/node/2712789', E_USER_DEPRECATED);
  }

  /**
   * {@inheritdoc}
   */
  public function persist(LanguageInterface $language) {
    // Default implementation persists nothing.
  }

}
