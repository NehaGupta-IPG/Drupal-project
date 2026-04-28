<?php

namespace Drupal\Core\Experimental;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\YamlFileLoader;
use Drupal\KernelTests\KernelTestBase;

/**
 * Trait for registering services for the mailer.
 *
 * Modules that want to experiment with the mailer should use this trait in
 * a service provider class to register the services. Kernel tests can use the
 * trait to test the services.
 *
 * @see \Drupal\Core\DependencyInjection\ServiceProviderInterface
 * @see \Drupal\core_mailer_services_test\CoreMailerServicesTestServiceProvider
 */
trait MailerServicesTrait {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $yaml_loader = new YamlFileLoader($container);
    if ($this instanceof KernelTestBase) {
      $root = $this->root;
    }
    else {
      $root = $container->get('kernel')->getAppRoot();
    }
    $yaml_loader->load($root . '/core/experimental/mailer.services.yml');
  }

}
