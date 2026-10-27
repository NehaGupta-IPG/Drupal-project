<?php

namespace Drupal\Core\Extension;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides methods to enable and disable feature flags.
 */
class FeatureFlagInstaller implements FeatureFlagInstallerInterface {

  use ModuleDependencyMessageTrait;
  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected RouteBuilderInterface $routeBuilder,
    #[Autowire(service: 'logger.channel.default')]
    protected LoggerInterface $logger,
    protected FeatureFlagExtensionList $flagExtensionList,
    #[Autowire(service: 'kernel')]
    protected DrupalKernelInterface $kernel,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function enable(array $flagList): bool {
    $extensionConfig = $this->configFactory->getEditable('core.extension');

    $flagData = $this->flagExtensionList->reset()->getList();
    $enabledFlags = $extensionConfig->get('feature_flag') ?: [];

    foreach ($flagList as $key) {
      // Throw an exception if the flag name is too long.
      if (strlen($key) > DRUPAL_EXTENSION_NAME_MAX_LENGTH) {
        throw new ExtensionNameLengthException("Feature flag name $key is over the maximum allowed length of " . DRUPAL_EXTENSION_NAME_MAX_LENGTH . ' characters.');
      }
    }

    $additionalFlags = [];
    foreach ($flagList as $key) {
      // Only process flags that are not already enabled.
      if ($extensionConfig->get("feature_flag.$key") !== NULL) {
        continue;
      }

      // The value is not used; the weight is ignored for flags currently.
      $extensionConfig->set("feature_flag.$key", 0);

      $additionalFlags[] = $key;
      // Record the fact that it was enabled.
      $this->logger->info('%flag feature flag enabled.', ['%flag' => $key]);
    }
    $extensionConfig->save();

    // Add new flags to the list of enabled flags.
    $registerFlags = array_merge(array_keys($enabledFlags), $additionalFlags);
    // Get list of extensions for the new list of flags.
    $registerFlags = array_intersect_key($flagData, array_flip($registerFlags));
    $this->resetSystem($registerFlags);

    // Invoke hook_feature_flags_enabled() after the flags have been enabled.
    $this->moduleHandler->invokeAll('feature_flags_enabled', [$additionalFlags]);

    return !empty($additionalFlags);
  }

  /**
   * {@inheritdoc}
   */
  public function disable(array $flagList): void {
    $extensionConfig = $this->configFactory->getEditable('core.extension');
    $enabledFlags = $extensionConfig->get('feature_flag') ?: [];
    $flagData = $this->flagExtensionList->reset()->getList();
    foreach ($flagList as $key) {
      if ($extensionConfig->get("feature_flag.$key") === NULL) {
        throw new UnknownExtensionException("Unknown feature flag: $key.");
      }
    }

    foreach ($flagList as $key) {
      $extensionConfig->clear("feature_flag.$key");
    }
    $extensionConfig->save();

    // Remove flags that were disabled from the list.
    $registerFlags = array_diff(array_keys($enabledFlags), $flagList);
    // Get list of extensions for the new list of flags.
    $registerFlags = array_intersect_key($flagData, array_flip($registerFlags));
    $this->resetSystem($registerFlags);

    $this->moduleHandler->invokeAll('feature_flags_disabled', [$flagList]);
  }

  /**
   * Resets some other systems like rebuilding the route information or caches.
   *
   * @param array<string, \Drupal\Core\Extension\Extension> $registerFlags
   *   Extension data for flags that should be registered, keyed by name.
   */
  protected function resetSystem(array $registerFlags) {
    $this->kernel->updateFeatureFlags($registerFlags);
    $container = $this->kernel->getContainer();
    $this->configFactory = $container->get('config.factory');
    $this->routeBuilder = $container->get('router.builder');
    $this->logger = $container->get('logger.channel.default');

    $container->get('stream_wrapper_manager')->register();
    // Clear all plugin caches.
    $container->get('plugin.cache_clearer')->clearCachedDefinitions();
    $this->routeBuilder->setRebuildNeeded();
  }

}
