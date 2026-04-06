<?php

namespace Drupal\Core\Installer;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Cache\MemoryBackendFactory;
use Drupal\Core\Cache\NullBackendFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\Lock\NullLockBackend;
use Drupal\Core\DependencyInjection\Compiler\RemovePassesTrait;
use Symfony\Component\DependencyInjection\Compiler\AliasDeprecatedPublicServicesPass;
use Symfony\Component\DependencyInjection\Compiler\AnalyzeServiceReferencesPass;
use Symfony\Component\DependencyInjection\Compiler\CheckArgumentsValidityPass;
use Symfony\Component\DependencyInjection\Compiler\CheckCircularReferencesPass;
use Symfony\Component\DependencyInjection\Compiler\CheckDefinitionValidityPass;
use Symfony\Component\DependencyInjection\Compiler\CheckExceptionOnInvalidReferenceBehaviorPass;
use Symfony\Component\DependencyInjection\Compiler\CheckReferenceValidityPass;
use Symfony\Component\DependencyInjection\Compiler\DefinitionErrorExceptionPass;
use Symfony\Component\DependencyInjection\Compiler\InlineServiceDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\RemoveAbstractDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\RemoveBuildParametersPass;
use Symfony\Component\DependencyInjection\Compiler\RemovePrivateAliasesPass;
use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\ReplaceAliasByActualDefinitionPass;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Service provider for the installer environment.
 *
 * This class is manually added by install_begin_request() via
 * $conf['container_service_providers'] and optimizes the container for
 * installation.
 *
 * Note that the early installer environment requires the further customizations
 * in InstallerServiceProvider.
 *
 * @see install_begin_request()
 * @see \Drupal\Core\Installer\InstallerServiceProvider
 */
class NormalInstallerServiceProvider implements ServiceProviderInterface {

  use RemovePassesTrait;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    global $install_state;

    // During the installer user 1 is a superuser.
    $container->setDefinition(InstallerAccessPolicy::class, (new Definition())->addTag('access_policy')->setPublic(FALSE));

    // Replace cache services with in-memory implementations. The results in
    // less queries to set caches which will only be cleared on the next module
    // install.
    $definition = $container->getDefinition('cache_factory');
    $definition->setClass(MemoryBackendFactory::class);
    $definition->setArguments([new Time()]);
    $definition->setMethodCalls([]);

    // When installing from existing config, disabling the config cache entirely
    // yields much faster install times.
    // Without this, MemoryBackend::invalidateTags must iterate over a very
    // large set of cached entries on every entity save — especially when
    // the site to install contains lots of languages.
    /* @see \Drupal\Core\Entity\EntityBase::postSave */
    /* @see \Drupal\Core\Cache\MemoryBackend::invalidateTags */
    if (!empty($install_state['config_install_path'])) {
      $cache_config_definition = $container->getDefinition('cache.config');
      $cache_config_definition->setFactory([new Reference('cache.backend.null'), 'get']);
      if (!$container->hasDefinition('cache.backend.null')) {
        $container->register('cache.backend.null', NullBackendFactory::class);
      }
    }

    // Replace lock service with no-op implementation as Drupal installation can
    // only occur in a single thread and the site should not be publicly
    // available.
    $container
      ->register('lock', NullLockBackend::class);

    // Remove the cache tags invalidator tag from the cache tags storage, so
    // that we don't call it when cache tags are invalidated in the installer.
    $container->getDefinition('cache_tags.invalidator.checksum')
      ->clearTag('cache_tags_invalidator');

    // Use performance-optimized extension lists.
    $container->getDefinition('extension.list.module')->setClass(InstallerModuleExtensionList::class);
    $container->getDefinition('extension.list.theme')->setClass(InstallerThemeExtensionList::class);

    // Don't register the lazy route provider in the super early installer.
    if (static::class === NormalInstallerServiceProvider::class) {
      $lazy_route_provider = $container->register('router.route_provider.installer');
      $lazy_route_provider
        ->setClass(InstallerRouteProviderLazyBuilder::class)
        ->setDecoratedService('router.route_provider')
        ->addArgument(new Reference('router.route_provider.installer.inner'))
        ->addArgument(new Reference('router.builder'))
        ->addTag('event_subscriber');
    }

    // During installation the container is rebuilt multiple times and never
    // dumped to disk, so many compiler passes are unnecessary.
    $this->removePasses($container, [
      // Removing passes: the container is never dumped, so removing unused
      // services, private aliases, and abstract definitions is wasted work.
      InlineServiceDefinitionsPass::class,
      RemoveUnusedDefinitionsPass::class,
      AnalyzeServiceReferencesPass::class,
      ReplaceAliasByActualDefinitionPass::class,
      RemovePrivateAliasesPass::class,
      RemoveAbstractDefinitionsPass::class,
      // Validation passes: any errors will be caught on the first production
      // container build after installation completes.
      CheckCircularReferencesPass::class,
      CheckDefinitionValidityPass::class,
      CheckReferenceValidityPass::class,
      CheckArgumentsValidityPass::class,
      CheckExceptionOnInvalidReferenceBehaviorPass::class,
      DefinitionErrorExceptionPass::class,
      // After-removing passes: the container is not dumped, so deprecation
      // aliasing and build parameter cleanup are unnecessary.
      AliasDeprecatedPublicServicesPass::class,
      RemoveBuildParametersPass::class,
    ]);
  }

}
