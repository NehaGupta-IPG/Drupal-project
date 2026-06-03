<?php

declare(strict_types=1);

namespace Drupal\Core\Menu\Attribute;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Declares a static menu link next to the controller method that serves it.
 *
 * This is the attribute-based equivalent of an entry in a *.links.menu.yml
 * file. Place it on a controller method (alongside a #[Route] attribute) to
 * declare the menu link for that route without repeating the route name:
 *
 * @code
 * #[Route(path: '/admin/config', name: 'system.admin_config', ...)]
 * #[MenuLink(
 *   title: new TranslatableMarkup('Configuration'),
 *   parent: 'system.admin',
 * )]
 * public function overview(): array { ... }
 * @endcode
 *
 * The plugin ID and the route the link points to are both derived from the
 * sibling #[Route] attribute when the method declares exactly one route. When
 * the method declares more than one route, the route this link points to must
 * be named explicitly with the $route property, and that name is also used as
 * the plugin ID unless an explicit $id is given.
 *
 * The attribute is repeatable: a single method backing several routes may
 * declare one menu link per route, each naming its $route.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class MenuLink {

  /**
   * Constructs a MenuLink attribute.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The static title for the menu link.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   The description of the menu link.
   * @param string|null $parent
   *   The plugin ID of the parent link, or NULL for a top-level link.
   * @param int $weight
   *   The weight of the link, used to order it among its siblings.
   * @param string|null $menu_name
   *   The machine name of the menu the link belongs to. Defaults to the
   *   menu link plugin default when omitted.
   * @param bool $expanded
   *   Whether the link should be expanded by default when it has children.
   * @param bool $enabled
   *   Whether the link is enabled (visible) by default.
   * @param array $options
   *   Link options, as understood by \Drupal\Core\Url.
   * @param string|null $id
   *   The menu link plugin ID. Derived from the sibling route name when
   *   omitted.
   * @param string|null $route
   *   The name of the route this link points to. Required only when the
   *   method declares more than one route; otherwise derived from the single
   *   sibling route.
   * @param array $route_parameters
   *   Parameters for the route variables when generating the link.
   */
  public function __construct(
    public readonly string|TranslatableMarkup $title = '',
    public readonly string|TranslatableMarkup $description = '',
    public readonly ?string $parent = NULL,
    public readonly int $weight = 0,
    public readonly ?string $menu_name = NULL,
    public readonly bool $expanded = FALSE,
    public readonly bool $enabled = TRUE,
    public readonly array $options = [],
    public readonly ?string $id = NULL,
    public readonly ?string $route = NULL,
    public readonly array $route_parameters = [],
  ) {}

}
