<?php

declare(strict_types=1);

namespace Drupal\jsonapi_translation;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\jsonapi_translation\Access\EntityAccessChecker;
use Drupal\jsonapi_translation\Controller\EntityResource;
use Drupal\jsonapi_translation\ParamConverter\EntityUuidConverter;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Replaces JSON:API services with translation-aware ones.
 *
 * @internal JSON:API Translation maintains no PHP API. The API is the HTTP API.
 *   This class may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 */
final class JsonapiTranslationServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $definition = $container->getDefinition('paramconverter.jsonapi.entity_uuid');
    $definition->setClass(EntityUuidConverter::class);
    $definition = $container->getDefinition('jsonapi.entity_access_checker');
    $definition->setClass(EntityAccessChecker::class);
    $definition = $container->getDefinition('jsonapi.entity_resource');
    $definition->setClass(EntityResource::class);
    $definition->addMethodCall('setLanguageManager', [new Reference('language_manager')]);
  }

}
