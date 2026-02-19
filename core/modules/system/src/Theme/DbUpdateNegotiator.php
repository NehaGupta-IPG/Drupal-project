<?php

namespace Drupal\system\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Sets the active theme for the database update pages.
 */
class DbUpdateNegotiator implements ThemeNegotiatorInterface {

  /**
   * The config factory.
   *
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The theme handler.
   *
   * @var ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Constructs a DbUpdateNegotiator.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param ThemeHandlerInterface $theme_handler
   *   The theme handler.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ThemeHandlerInterface $theme_handler) {
    $this->configFactory = $config_factory;
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return $route_match->getRouteName() == 'system.db_update';
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    // The update page always uses Claro to ensure stability.
    return 'claro';
  }

}
