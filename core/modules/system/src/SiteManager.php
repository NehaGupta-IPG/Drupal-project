<?php

namespace Drupal\system;

use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Site Manager Service.
 */
class SiteManager {

  /**
   * An array of site property data.
   *
   * @var array
   */
  protected $properties;

  /**
   * An array of SiteProperty plugins.
   *
   * @var array
   */
  protected $propertyPlugins;

  /**
   * The plugin.manager.site_property service.
   *
   * @var \Drupal\system\SitePropertyPluginManager
   */
  protected $propertyPluginManager;

  /**
   * System manager service.
   *
   * @var SystemManager
   */
  protected $systemManager;

  /**
   * The request stack.
   *
   * @var RequestStack
   */
  protected $requestStack;

  /**
   * The config service.
   *
   * @var ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructs a SiteManager object.
   *
   * @param SystemManager $system_manager
   *   The system manager.
   * @param RequestStack $request_stack
   *   The request stack.
   * @param ConfigFactory $request_stack
   *   The request stack.
   */
  public function __construct(SystemManager $system_manager, RequestStack $request_stack, ConfigFactory $config_factory, SitePropertyPluginManager $plugin_manager) {
    $this->systemManager = $system_manager;
    $this->requestStack = $request_stack;
    $this->configFactory = $config_factory;
    $this->propertyPluginManager = $plugin_manager;
    $this->propertyPlugins = $this->getPropertyPlugins();
  }

  public static function stripScheme($url) {
    return strtr($url, [
      'https://' => '',
      'http://' => '',
    ]);
  }

  /**
   * True if the current URL matches the canonical URL.
   * @return void
   */
  public function isMain() {
    return static::stripScheme($this->getCurrentUrl()) == static::stripScheme($this->getMainUrl());
  }

  /**
   * Returns the scheme and host for the current request.
   * @return string
   */
  public function getCurrentUrl() {
    return $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
  }


  /**
   * Returns the scheme and host for the current request.
   * @return string
   */
  public function getMainUrl() {
    return $this->configFactory->get('system.site')->get('canonical_url');
  }

  /**
   * Returns the site name from config.
   * @return string
   */
  public function getName() {
    return $this->configFactory->get('system.site')->get('name');
  }

  /**
   * Returns the site slogan from config.
   * @return string
   */
  public function getSlogan() {
    return $this->configFactory->get('system.site')->get('slogan');
  }

  /**
   * @return array
   */
  public function getPropertyPlugins() {
    if (empty($this->propertyPlugins)) {
      $plugin_definitions = $this->propertyPluginManager->getDefinitions();
      foreach ($plugin_definitions as $name => $plugin_definition) {
        $plugin = $this->propertyPluginManager->createInstance($plugin_definition['id']);
        $this->propertyPlugins[$name] = $plugin;
      }
    }
    return $this->propertyPlugins;
  }

  /**
   * @return void
   */
  public function getProperties() {
    if (empty($this->properties)) {
      foreach ($this->propertyPlugins as $name => $plugin) {
        $this->properties[$name] = $plugin->value();
      }
    }
    return $this->properties;
  }
}
