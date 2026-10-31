<?php

declare(strict_types=1);

namespace Drupal\navigation\Plugin\TopBarItem;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\navigation\Attribute\TopBarItem;
use Drupal\navigation\NavigationRenderer;
use Drupal\navigation\TopBarItemBase;
use Drupal\navigation\TopBarRegion;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Page Actions basic top bar item.
 *
 * @internal
 */
#[TopBarItem(
  id: 'page_actions',
  region: TopBarRegion::Actions,
  label: new TranslatableMarkup('Page Actions'),
)]
final class PageActions extends TopBarItemBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a PageActions object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\navigation\NavigationRenderer $navigationRenderer
   *   The navigation renderer.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected NavigationRenderer $navigationRenderer,
    protected RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(NavigationRenderer::class),
      $container->get(RouteMatchInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [
      '#cache' => [
        'contexts' => ['route'],
      ],
    ];

    // Local tasks for content entities.
    if (!$this->navigationRenderer->hasLocalTasks()) {
      return $build;
    }

    // @todo #3590502 Remove local tasks without breaking tests.
    // $page_actions = $this->navigationRenderer->getLocalTasks();

    $build += [
      '#theme' => 'top_bar_page_actions',
      // '#page_actions' => $page_actions['page_actions'],
    ];

    return $build;
  }

}
