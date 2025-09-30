<?php

namespace Drupal\Core\PathProcessor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\FrontPagePathTrait;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Processes the inbound path by resolving it to the front page if empty.
 */
class PathProcessorFront implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  use FrontPagePathTrait;

  /**
   * Constructs a PathProcessorFront object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving the site front page configuration.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    if ($path === '/') {
      $path = $this->getFrontPagePath();
      if (empty($path)) {
        // We have to return a valid path but / does not have a route and config
        // might be broken so stop execution.
        throw new NotFoundHttpException();
      }
      $components = parse_url($path);
      // Remove query string and fragment.
      $path = $components['path'];
      // Merge query parameters from front page configuration value
      // with URL query, so that actual URL takes precedence.
      if (!empty($components['query'])) {
        parse_str($components['query'], $parameters);
        $parameters = array_replace($parameters, $request->query->all());
        $request->query->replace($parameters);
      }
    }
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    if ($path === $this->getFrontPagePath()) {
      $path = '/';
      // Add cache context to ensure cache invalidation when front page changes.
      if ($bubbleable_metadata) {
        $bubbleable_metadata->addCacheContexts(['url.path.is_front']);
        $bubbleable_metadata->addCacheTags(['config:system.site']);
      }
    }
    return $path;
  }

}
