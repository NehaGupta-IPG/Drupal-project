<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Core\Plugin\Factory\ContainerFactory;

/**
 * Defines a plugin manager to deal with emails.
 *
 * Modules can define emails in a MODULE_NAME.emails.yml file contained
 * in the module's base directory. Each email has the following structure:
 *
 * @code
 *   MACHINE_NAME:
 *     label: STRING
 *     description: STRING
 *     module: STRING
 *     key: STRING
 * @endcode
 *
 * @see \Drupal\Core\Mailer\EmailDefault
 * @see \Drupal\Core\Mailer\EmailInterface
 */
class EmailManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  protected $defaults = [
    // The email id. Set by the plugin system based on the top-level YAML key.
    'id' => '',
    // The email label.
    'label' => '',
    // The email description.
    'description' => '',
    // The email sending module.
    'module' => '',
    // The email key used to identify different emails sent from a module.
    'key' => '',
    // Default plugin class.
    'class' => DefaultEmail::class,
  ];

  /**
   * Constructs EmailManager object.
   */
  public function __construct(ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend) {
    $this->factory = new ContainerFactory($this, EmailPluginInterface::class);
    $this->moduleHandler = $module_handler;
    $this->alterInfo('email_info');
    $this->setCacheBackend($cache_backend, 'email_plugins');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery(): DiscoveryInterface {
    if (!isset($this->discovery)) {
      $discovery = new YamlDiscovery('email', $this->moduleHandler->getModuleDirectories());
      $discovery->addTranslatableProperty('label', 'label_context');
      $discovery->addTranslatableProperty('description', 'description_context');
      $discovery = new ContainerDerivativeDiscoveryDecorator($discovery);
      $this->discovery = $discovery;
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id): void {
    parent::processDefinition($definition, $plugin_id);

    if (empty($definition['module'])) {
      $definition['module'] = $definition['provider'];
    }
  }

}
