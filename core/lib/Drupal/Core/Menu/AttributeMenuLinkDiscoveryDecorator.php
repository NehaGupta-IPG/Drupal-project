<?php

declare(strict_types=1);

namespace Drupal\Core\Menu;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryTrait;
use Drupal\Core\Discovery\AttributeScanner;
use Drupal\Core\Menu\Attribute\MenuLink;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;

/**
 * Adds menu links declared with #[MenuLink] to a decorated discovery.
 *
 * This scans controllers for the #[MenuLink] attribute (see
 * \Drupal\Core\Menu\Attribute\MenuLink) and merges the resulting definitions
 * with those of the decorated discovery, which is typically the YAML discovery
 * that reads *.links.menu.yml files. Attribute-declared and YAML-declared links
 * therefore share a single plugin ID namespace and are processed identically
 * downstream; on an ID collision the decorated (YAML) definition wins.
 */
class AttributeMenuLinkDiscoveryDecorator implements DiscoveryInterface {

  use DiscoveryTrait;

  /**
   * Constructs an AttributeMenuLinkDiscoveryDecorator object.
   *
   * @param \Drupal\Component\Plugin\Discovery\DiscoveryInterface $decorated
   *   The discovery to decorate.
   * @param \Traversable<string, string> $namespaces
   *   The root paths keyed by the corresponding namespace to scan, typically
   *   the 'container.namespaces' parameter.
   */
  public function __construct(
    protected readonly DiscoveryInterface $decorated,
    protected readonly \Traversable $namespaces,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    // YAML-declared links take precedence on a plugin ID collision.
    return $this->decorated->getDefinitions() + $this->getAttributeDefinitions();
  }

  /**
   * Builds the menu link definitions declared with the #[MenuLink] attribute.
   *
   * @return array<string, array>
   *   Menu link plugin definitions keyed by plugin ID.
   */
  protected function getAttributeDefinitions(): array {
    $definitions = [];
    $scanner = new AttributeScanner($this->namespaces);
    foreach ($scanner->scan(MenuLink::class, 'Controller') as $class) {
      $menu_links = AttributeScanner::getAttributeInstances($class, MenuLink::class);
      if (!$menu_links) {
        continue;
      }
      $routes = AttributeScanner::getAttributeInstances($class, RouteAttribute::class);
      foreach ($menu_links as $method => $method_menu_links) {
        foreach ($method_menu_links as $menu_link) {
          $definition = $this->buildDefinition($menu_link, $class, $method, $routes[$method] ?? []);
          $definitions[$definition['id']] = $definition;
        }
      }
    }
    return $definitions;
  }

  /**
   * Builds a single menu link definition from an attribute instance.
   *
   * @param \Drupal\Core\Menu\Attribute\MenuLink $menu_link
   *   The menu link attribute instance.
   * @param \ReflectionClass<object> $class
   *   The controller class the attribute was declared on.
   * @param string $method
   *   The name of the method the attribute was declared on.
   * @param list<\Symfony\Component\Routing\Attribute\Route> $routes
   *   The Route attributes declared on the same method.
   *
   * @return array
   *   A menu link plugin definition.
   */
  protected function buildDefinition(MenuLink $menu_link, \ReflectionClass $class, string $method, array $routes): array {
    $route_name = $menu_link->route ?? $this->resolveRouteName($routes, $class, $method);
    $id = $menu_link->id ?? $route_name;
    if ($id === NULL) {
      throw new \LogicException(sprintf('The #[MenuLink] attribute on "%s::%s()" must define an "id", or be placed on a method with a single #[Route] attribute to derive one.', $class->getName(), $method));
    }

    $definition = [
      'id' => $id,
      'title' => $menu_link->title,
      'description' => $menu_link->description,
      'weight' => $menu_link->weight,
      'expanded' => (int) $menu_link->expanded,
      'enabled' => (int) $menu_link->enabled,
      'options' => $menu_link->options,
      'route_parameters' => $menu_link->route_parameters,
      'provider' => $this->getProvider($class->getName()),
    ];
    if ($route_name !== NULL) {
      $definition['route_name'] = $route_name;
    }
    if ($menu_link->parent !== NULL) {
      $definition['parent'] = $menu_link->parent;
    }
    if ($menu_link->menu_name !== NULL) {
      $definition['menu_name'] = $menu_link->menu_name;
    }
    return $definition;
  }

  /**
   * Resolves the route name from the Route attributes on the same method.
   *
   * @param list<\Symfony\Component\Routing\Attribute\Route> $routes
   *   The Route attributes declared on the method.
   * @param \ReflectionClass<object> $class
   *   The controller class, for error messages.
   * @param string $method
   *   The method name, for error messages.
   *
   * @return string|null
   *   The single sibling route name, or NULL if the method declares no route.
   *
   * @throws \LogicException
   *   When the method declares more than one route, as the link cannot know
   *   which one it points to.
   */
  protected function resolveRouteName(array $routes, \ReflectionClass $class, string $method): ?string {
    $route_names = array_values(array_filter(array_map(static fn (RouteAttribute $route) => $route->name, $routes)));
    if (count($route_names) > 1) {
      throw new \LogicException(sprintf('The #[MenuLink] attribute on "%s::%s()" must specify a "route" because the method declares more than one route.', $class->getName(), $method));
    }
    return $route_names[0] ?? NULL;
  }

  /**
   * Extracts the provider (module) name from a Drupal namespace.
   *
   * @param string $class
   *   The fully qualified class name.
   *
   * @return string|null
   *   The provider name, or NULL if it could not be determined.
   */
  protected function getProvider(string $class): ?string {
    preg_match('/^Drupal\\\\([\w]+)\\\\/', $class, $matches);
    return isset($matches[1]) ? mb_strtolower($matches[1]) : NULL;
  }

}
