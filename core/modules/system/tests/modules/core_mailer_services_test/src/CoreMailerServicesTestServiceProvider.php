<?php

declare(strict_types=1);

namespace Drupal\core_mailer_services_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\DependencyInjection\YamlFileLoader;

/**
 * Service provider for testing the mailer services.
 */
class CoreMailerServicesTestServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $yaml_loader = new YamlFileLoader($container);
    $root = $container->get('kernel')->getAppRoot();
    $yaml_loader->load($root . '/core/experimental/mailer.services.yml');
  }

}
