<?php

declare(strict_types=1);

namespace Drupal\menu_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\Attribute\Route;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Controller routines for menu_test routes.
 */
class MenuTestController extends ControllerBase {

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The theme negotiator.
   *
   * @var \Drupal\Core\Theme\ThemeNegotiatorInterface
   */
  protected $themeNegotiator;

  /**
   * The active route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs the MenuTestController object.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Theme\ThemeNegotiatorInterface $theme_negotiator
   *   The theme negotiator.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(ThemeManagerInterface $theme_manager, ThemeNegotiatorInterface $theme_negotiator, RouteMatchInterface $route_match) {
    $this->themeManager = $theme_manager;
    $this->themeNegotiator = $theme_negotiator;
    $this->routeMatch = $route_match;
  }

  /**
   * Some known placeholder content which can be used for testing.
   *
   * @return string
   *   A string that can be used for comparison.
   */
  #[Route(
    path: '/menu_name_test',
    name: 'menu_test.menu_name_test',
    title: new TranslatableMarkup('Test menu_name router item'),
    requirements: ['_access' => 'TRUE'],
  )]
  #[Route(
    path: '/menu_callback_title',
    name: 'menu_test.menu_callback_title',
    title: new TranslatableMarkup('Menu Callback Title'),
    requirements: ['_access' => 'TRUE'],
  )]
  #[Route(
    path: '/menu_callback_description/description-plain',
    name: 'menu_test.callback_description_plain',
    title: new TranslatableMarkup('Menu item with a regular description'),
    requirements: ['_access' => 'TRUE'],
  )]
  #[Route(
    path: '/menu_no_title_callback',
    name: 'menu_test.menu_no_title_callback',
    title: new TranslatableMarkup('A title with @placeholder'),
    requirements: ['_access' => 'TRUE'],
  )]
  #[Route(
    path: '/menu-test/{exotic}',
    name: 'menu_test.exotic_path',
    requirements: ['exotic' => '.+', '_access' => 'TRUE'],
  )]
  #[Route(
    path: '/menu-title-test/case1',
    name: 'menu_test.title_test_case1',
    title: new TranslatableMarkup('Example title - Case 1'),
    requirements: ['_access' => 'TRUE'],
  )]
  #[Route(
    path: '/menu-title-test/case2',
    name: 'menu_test.title_test_case2',
    title: new TranslatableMarkup('Example title'),
    requirements: ['_access' => 'TRUE'],
    defaults: ['_title_callback' => '\\Drupal\\menu_test\\Controller\\MenuTestController::titleCallback'],
  )]
  #[Route(
    path: '/menu-title-test/case3',
    name: 'menu_test.title_test_case3',
    title: new TranslatableMarkup('Bike sheds full of blue smurfs'),
    requirements: ['_access' => 'TRUE'],
    defaults: [
      '_title_callback' => '\\Drupal\\menu_test\\Controller\\MenuTestController::titleCallback',
      '_title_arguments' => ['title' => 'Example title', 'case_number' => '3'],
    ],
  )]
  #[Route(
    path: '/menu-test/context',
    name: 'menu_test.context',
    requirements: ['_access' => 'TRUE'],
  )]
  #[Route(
    path: '/menu-test/custom',
    name: 'menu_test.custom',
    requirements: ['_access' => 'TRUE'],
  )]
  #[Route(
    path: '/menu-test/breadcrumb1',
    name: 'menu_test.breadcrumb1',
    title: new TranslatableMarkup('<script>alert(12);</script>'),
    requirements: ['_access' => 'TRUE'],
  )]
  #[Route(
    path: '/menu-test/breadcrumb1/breadcrumb2',
    name: 'menu_test.breadcrumb2',
    requirements: ['_access' => 'TRUE'],
    defaults: ['_title_callback' => '\\Drupal\\menu_test\\Controller\\MenuTestController::breadcrumbTitleCallback'],
  )]
  #[Route(
    path: '/menu-test/breadcrumb1/breadcrumb2/breadcrumb3',
    name: 'menu_test.breadcrumb3',
    title: new TranslatableMarkup('Normal title'),
    requirements: ['_access' => 'TRUE'],
  )]
  #[Route(
    path: '/menu-test/skippable-breadcrumb',
    name: 'menu_test.skippable-breadcrumb',
    title: new TranslatableMarkup('Normal title'),
    requirements: ['_access' => 'TRUE'],
  )]
  public function menuTestCallback() {
    return ['#markup' => 'This is the menuTestCallback content.'];
  }

  /**
   * A title callback method for test routes.
   *
   * @param array $_title_arguments
   *   Optional array from the route defaults.
   * @param string $_title
   *   Optional _title string from the route defaults.
   *
   * @return string
   *   The route title.
   */
  public function titleCallback(array $_title_arguments = [], $_title = '') {
    $_title_arguments += ['case_number' => '2', 'title' => $_title];
    // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
    return $this->t($_title_arguments['title']) . ' - Case ' . $_title_arguments['case_number'];
  }

  /**
   * Page callback: Tests the theme negotiation functionality.
   *
   * @param bool $inherited
   *   TRUE when the requested page is intended to inherit
   *   the theme of its parent.
   *
   * @return string
   *   A string describing the requested custom theme and actual
   *   theme being used
   *   for the current page request.
   */
  #[Route(
    path: '/menu-test/theme-callback/{inherited}',
    name: 'menu_test.theme_callback',
    title: new TranslatableMarkup('Page that displays different themes'),
    requirements: ['_access' => 'TRUE'],
  )]
  #[Route(
    path: '/menu-test/no-theme-callback',
    name: 'menu_test.no_theme_callback',
    title: new TranslatableMarkup('Page that displays different themes without using a theme negotiation.'),
    requirements: ['_access' => 'TRUE'],
    defaults: ['inherited' => FALSE],
  )]
  #[Route(
    path: '/menu-test/theme-callback/{inherited}/inheritance',
    name: 'menu_test.theme_callback_inheritance',
    title: new TranslatableMarkup('Page that tests theme negotiation inheritance.'),
    requirements: ['_access' => 'TRUE'],
  )]
  public function themePage($inherited) {
    $theme_key = $this->themeManager->getActiveTheme()->getName();
    // Now we check what the theme negotiator service returns.
    $active_theme = $this->themeNegotiator
      ->determineActiveTheme($this->routeMatch);
    $output = "Active theme: $active_theme. Actual theme: $theme_key.";
    if ($inherited) {
      $output .= ' Theme negotiation inheritance is being tested.';
    }
    return ['#markup' => $output];
  }

  /**
   * A title callback for XSS breadcrumb check.
   *
   * @return string
   *   A string that can be used for comparison.
   */
  public function breadcrumbTitleCallback() {
    return '<script>alert(123);</script>';
  }

}
