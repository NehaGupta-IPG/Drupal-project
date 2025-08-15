<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\Mailer\MailerInterface;
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

  public function __construct(
    ModuleHandlerInterface $module_handler,
    #[Autowire(service: 'cache.discovery')]
    CacheBackendInterface $cache_backend,
    #[AutowireServiceClosure(EmailRendererInterface::class)]
    protected readonly \Closure $emailRenderer,
    #[AutowireServiceClosure('logger.channel.mail')]
    protected readonly \Closure $logger,
    #[AutowireServiceClosure(MailerInterface::class)]
    protected readonly \Closure $mailer,
  ) {
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
  public function getEmailDefinition(string $id): EmailDefinition {
    return $this->getDefinition($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilder(): EmailBuilderInterface {
    return new EmailBuilder($this->emailRenderer, fn() => $this, $this->mailer, $this->logger);
  }

  /**
   * {@inheritdoc}
   */
  public function createEmail(string $id, array $configuration = []): Email {
    return $this->createInstance($id, $configuration);
  }

}
