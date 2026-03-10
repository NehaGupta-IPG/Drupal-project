<?php

namespace Drupal\path_alias\PathProcessor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes the inbound and outbound path using path alias lookups.
 */
class AliasPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * An alias manager for looking up the system path.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * A config factory for retrieving required config settings.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Constructs a AliasPathProcessor object.
   *
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   An alias manager for looking up the system path.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory to get the system config from.
   */
  public function __construct(AliasManagerInterface $alias_manager, ConfigFactoryInterface $configFactory) {
    $this->aliasManager = $alias_manager;
    $this->config = $configFactory->get('system.site');
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $path = $this->aliasManager->getPathByAlias($path);
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    if (empty($options['alias'])) {
      $langcode = isset($options['language']) ? $options['language']->getId() : NULL;
      $path = $this->aliasManager->getAliasByPath($path, $langcode);
      // Ensure the resulting path has at most one leading slash, to prevent it
      // becoming an external URL without a protocol like //example.com. This
      // is done in \Drupal\Core\Routing\UrlGenerator::generateFromRoute()
      // also, to protect against this problem in arbitrary path processors,
      // but it is duplicated here to protect any other URL generation code
      // that might call this method separately.
      if (str_starts_with($path, '//')) {
        $path = '/' . ltrim($path, '/');
      }
    }

    // The special path '<front>' links to the default front page.
    if ($path === '/<front>') {
      return '/';
    }

    // Look for paths that are not already the front page.
    if ($path !== '/') {
      $front = $this->config->get('page.front');
      $langcode = !empty($options['language']) ? $options['language']->getId() : NULL;

      // Get path and alias for the configured frontpage setting
      $front_path = $this->aliasManager->getPathByAlias($front, $langcode);
      $front_alias = $this->aliasManager->getAliasByPath($front, $langcode);

      // Replace the path and alias with default frontpage path
      if (!empty($front_path) && $path === $front_path) {
        return '/';
      }
      if (!empty($front_alias) && $path === $front_alias) {
        return '/';
      }
    }

    return $path;
  }

}
