<?php

namespace Drupal\jsonapi;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\jsonapi\DependencyInjection\Compiler\RegisterSerializationClassesCompilerPass;

/**
 * Adds 'api_json' as known format and prevents its use in the REST module.
 *
 * @internal JSON:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 * @see jsonapi.api.php
 */
class JsonapiServiceRegister implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $container->addCompilerPass(new RegisterSerializationClassesCompilerPass());
  }

}
