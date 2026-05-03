<?php

namespace Drupal\Core\Extension;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;

/**
 * Provides a list of installation feature flags.
 *
 * @internal
 *   This class is not yet stable and therefore there are no guarantees that the
 *   internal implementations including constructor signature and protected
 *   properties / methods will not change over time. This will be reviewed after
 *   https://www.drupal.org/project/drupal/issues/2940481
 */
class FeatureFlagExtensionList extends ExtensionList {

  /**
   * {@inheritdoc}
   */
  protected $defaults = [
    'description' => '',
    'package' => 'Other',
    'version' => NULL,
    'php' => \Drupal::MINIMUM_PHP,
  ];

  /**
   * Cached list of installed feature flags.
   *
   * @var string[]|null
   */
  protected ?array $installedFlags;

  public function __construct(
    string $root,
    string $type,
    CacheBackendInterface $cache,
    InfoParserInterface $infoParser,
    ModuleHandlerInterface $moduleHandler,
    StateInterface $state,
    protected readonly ConfigFactoryInterface $configFactory,
    ?string $installProfile,
  ) {
    parent::__construct($root, $type, $cache, $infoParser, $moduleHandler, $state, $installProfile);
  }

  /**
   * {@inheritdoc}
   */
  protected function getInstalledExtensionNames() {
    // Cache the installed feature flags to avoid multiple calls to the config
    // system.
    if (!isset($this->installedFlags)) {
      $this->installedFlags = $this->configFactory->get('core.extension')->get('feature_flag') ?: [];
    }
    return array_keys($this->installedFlags);
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    parent::reset();
    $this->installedFlags = NULL;
    return $this;
  }

}
