<?php

namespace Drupal\Core\Installer;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Cache\NullBackendFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
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
 * $conf['container_service_providers'] and handles both the early installer
 * phase (before the base system is installed) and the late installer phase
 * (once the database is available).
 *
 * The phase is determined by $install_state['base_system_verified']:
 * - FALSE/absent: Early phase with in-memory and fallback services.
 * - TRUE: Late phase with database-backed services.
 *
 * This provider is also registered by DrupalKernel::discoverServiceProviders()
 * as a broken-site fallback and by TestRunnerKernel. In those cases,
 * $install_state is not set, so the early phase is used.
 *
 * @see install_begin_request()
 * @see \Drupal\Core\DrupalKernel::discoverServiceProviders()
 * @see \Drupal\Core\Test\TestRunnerKernel::discoverServiceProviders()
 */
class InstallerServiceProvider implements ServiceProviderInterface, ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    global $install_state;
    $base_verified = !empty($install_state['base_system_verified']);

    if (!$base_verified) {
      // Early phase: use fallback config storage that can write to the database
      // once available, with InstallStorage fallback for reads.
      $container->register('config.storage', InstallerFallbackConfigStorage::class);

      // Replace services with in-memory implementations.
      $container->register('keyvalue', 'Drupal\Core\KeyValueStore\KeyValueMemoryFactory');
      $container->register('keyvalue.expirable', 'Drupal\Core\KeyValueStore\KeyValueNullExpirableFactory');

      // Replace services with no-op implementations.
      $container
        ->register('url_generator', 'Drupal\Core\Routing\NullGenerator')
        ->addArgument(new Reference('request_stack'));
      $container
        ->register('path_processor_manager', 'Drupal\Core\PathProcessor\NullPathProcessorManager');
      $container
        ->register('router.dumper', 'Drupal\Core\Routing\NullMatcherDumper');

      // Prevent the static route builders from core from listening to
      // the event.
      // @todo Convert installer steps into routes; add an installer.routing.yml.
      $definition = $container->getDefinition('router.builder.attributes');
      $definition->clearTag('event_subscriber');
      $definition = $container->getDefinition('router.builder.yaml');
      $definition->clearTag('event_subscriber');

      // The file_parsing bin has no cache.bin tag, so the memory default set
      // below does not reach it and the cache factory would hand out the
      // database backend. There is no database yet, so use a memory backend.
      $definition = $container->getDefinition('cache.file_parsing');
      $definition->setClass(MemoryBackend::class);
      $definition->setArguments([new Time()]);
      $definition->setFactory(NULL);
    }
    else {
      // Late installer phase: the database is available, so the real
      // config.storage and keyvalue services from core.services.yml are used.
      // Register the lazy route provider decorator to avoid premature route
      // building.
      $container->register('router.route_provider.installer')
        ->setClass(InstallerRouteProviderLazyBuilder::class)
        ->setDecoratedService('router.route_provider')
        ->addArgument(new Reference('router.route_provider.installer.inner'))
        ->addArgument(new Reference('router.builder'))
        ->addTag('event_subscriber');
    }

    // Common services for both phases.

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

    $pass_config = $container->getCompilerPassConfig();
    $pass_config->setRemovingPasses(array_filter($pass_config->getRemovingPasses(), function ($pass) {
      // These passes are not necessary during installation.
      // @see \Symfony\Component\DependencyInjection\Compiler\PassConfig
      return !($pass instanceof InlineServiceDefinitionsPass ||
               $pass instanceof RemoveUnusedDefinitionsPass ||
               $pass instanceof AnalyzeServiceReferencesPass ||
               $pass instanceof ReplaceAliasByActualDefinitionPass);
    }));
    $pass_config->setAfterRemovingPasses(array_filter($pass_config->getAfterRemovingPasses(), function ($pass) {
      // Remove ResolveHotPathPass as Drupal's container dumper does not support
      // it.
      // @see \Symfony\Component\DependencyInjection\Compiler\PassConfig
      return !($pass instanceof ResolveHotPathPass);
    }));
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    global $install_state;
    $base_verified = !empty($install_state['base_system_verified']);

    if (!$base_verified) {
      // Disable Twig cache (php storage does not exist yet).
      $twig_config = $container->getParameter('twig.config');
      $twig_config['cache'] = FALSE;
      $container->setParameter('twig.config', $twig_config);
    }
  }

}
