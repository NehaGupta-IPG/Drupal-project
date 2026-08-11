<?php

namespace Drupal\Core\PathProcessor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Processes the inbound path by resolving it to the front page if empty.
 */
class PathProcessorFront implements InboundPathProcessorInterface {

  /**
   * A config factory for retrieving required config settings.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a PathProcessorFront object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   A config factory for retrieving the site front page configuration.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(ConfigFactoryInterface $config, ?LanguageManagerInterface $language_manager = NULL) {
    $this->config = $config;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    if ($path === '/') {
      $path = NULL;
      if ($path_langcode = $request->attributes->get('path_langcode')) {
        if ($this->languageManager instanceof ConfigurableLanguageManagerInterface) {
          $config_override = $this->languageManager->getLanguageConfigOverride($path_langcode, 'system.site');
          $path = $config_override->get('page.front');
        }
      }
      if (empty($path)) {
        $path = $this->config->get('system.site')->get('page.front');
      }
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

}
