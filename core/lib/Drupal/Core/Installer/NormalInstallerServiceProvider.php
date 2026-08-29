<?php

namespace Drupal\Core\Installer;

use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Cache\NullBackendFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\Lock\NullLockBackend;
use Symfony\Component\DependencyInjection\Compiler\AnalyzeServiceReferencesPass;
use Symfony\Component\DependencyInjection\Compiler\InlineServiceDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\ReplaceAliasByActualDefinitionPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveHotPathPass;
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

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    global $install_state;

    // During the installer user 1 is a superuser.
    $container->setDefinition(InstallerAccessPolicy::class, (new Definition())->addTag('access_policy')->setPublic(FALSE));

    // Explicitly configure all cache bins to use the memory backend. This
    // results in less queries to set caches which will only be cleared on the
    // next module install.
    foreach (array_keys($container->findTaggedServiceIds('cache.bin')) as $id) {
      $definition = $container->getDefinition($id);
      $tags = $definition->getTags();
      $tags['cache.bin'][0]['default_backend'] = 'cache.backend.memory';
      $definition->setTags($tags);
    }

    // Separately override the file_parsing bin as that doesn't use a
    // tag/parameter. Only do so in the early installer, which may have no
    // database. Once the base system is verified the real backend is kept:
    // items in this bin persist between cache clears (they are invalidated
    // by file modification time instead), so writes during the later
    // installer are reused across installer requests and warm the cache for
    // the installed site.
    if (empty($install_state['base_system_verified'])) {
      $definition = $container->getDefinition('cache.file_parsing');
      $definition->setClass(MemoryBackend::class);
      // Use a service reference rather than an object instance, so that the
      // container can still be dumped.
      $definition->setArguments([new Reference('datetime.time')]);
      $definition->setFactory(NULL);
    }

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

    $pass_config = $container->getCompilerPassConfig();
    // Skipping these passes is only safe while the container is never dumped:
    // a dumped container needs aliases and private services fully resolved.
    // Once the base system is installed, the installer dumps its container so
    // it can be reused across requests, so the passes must run then.
    // @see install_begin_request()
    if (empty($install_state['base_system_verified'])) {
      $pass_config->setRemovingPasses(array_filter($pass_config->getRemovingPasses(), function ($pass) {
        // Remove InlineServiceDefinitionsPass, RemoveUnusedDefinitionsPass,
        // AnalyzeServiceReferencesPass and ReplaceAliasByActualDefinitionPass
        // as these are not necessary during the early installer.
        // @see \Symfony\Component\DependencyInjection\Compiler\PassConfig
        return !($pass instanceof InlineServiceDefinitionsPass ||
                 $pass instanceof RemoveUnusedDefinitionsPass ||
                 $pass instanceof AnalyzeServiceReferencesPass ||
                 $pass instanceof ReplaceAliasByActualDefinitionPass);
      }));
    }
    $pass_config->setAfterRemovingPasses(array_filter($pass_config->getAfterRemovingPasses(), function ($pass) {
      // Remove ResolveHotPathPass as Drupal's container dumper does not support
      // it.
      // @see \Symfony\Component\DependencyInjection\Compiler\PassConfig
      return !($pass instanceof ResolveHotPathPass);
    }));
  }

}
