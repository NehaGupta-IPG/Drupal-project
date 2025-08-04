<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Symfony\Component\Mime\Email;

/**
 * Defines a plugin manager to collect information on emails.
 *
 * Modules can define emails in a MODULE_NAME.email.yml file contained
 * in the module's base directory. Each email has the following structure:
 *
 * @code
 *   MACHINE_NAME:
 *     label: STRING
 *     description: STRING
 *     module: STRING
 *     key: STRING
 *     defaults: ARRAY
 * @endcode
 */
class EmailPluginManager extends DefaultPluginManager implements EmailManagerInterface {

  public function __construct(ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend) {
    $this->factory = new EmailFactory($this);
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
      $discovery
        ->addTranslatableProperty('label')
        ->addTranslatableProperty('description');
      $discovery = new EmailDiscoveryDecorator($discovery);
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmailDefinition(string $id, array|\Closure|null $defaults = NULL): EmailDefinition {
    $declaredDefinition = $this->getDefinition($id);
    assert($declaredDefinition instanceof EmailDefinition);
    $declaredDefaults = $declaredDefinition->getDefaults();
    $callerDefaults = match(TRUE) {
      is_array($defaults) => $defaults,
      $defaults instanceof \Closure => [EmailDefinition::CONTROLLER_NAME => $defaults],
      default => [],
    };

    return new EmailDefinition([
      'id' => $declaredDefinition->id(),
      'class' => $declaredDefinition->getClass(),
      'provider' => $declaredDefinition->getProvider(),
      'label' => $declaredDefinition->getLabel(),
      'description' => $declaredDefinition->getDescription(),
      'module' => $declaredDefinition->getModule(),
      'key' => $declaredDefinition->getKey(),
      'defaults' => $declaredDefaults + $callerDefaults,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function createEmail(string $id, array $configuration = []): Email {
    return $this->createInstance($id, $configuration);
  }

}
