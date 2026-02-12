<?php

declare(strict_types=1);

namespace Drupal\toolbar_test_toolbar_items\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derivative class that provides dummy menu links for testing toolbar.
 */
class MenuLinkDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The base plugin ID this derivative is for.
   *
   * @var string
   */
  protected $basePluginId;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Creates a MenuLinkDeriver.
   *
   * @param string $base_plugin_id
   *   The base plugin id.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct($base_plugin_id, StateInterface $state) {
    $this->basePluginId = $base_plugin_id;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $toolbar_test_menu_items = $this->state->get('toolbar_test_menu_items', []);

    foreach ($toolbar_test_menu_items as $key => $menu_item_data) {
      if (!is_array($menu_item_data)) {
        $menu_item_data = [];
      }

      $menu_item_data += [
        'title' => $this->t('A toolbar menu item that links to the user page'),
        'route_name' => 'user.page',
        'route_parameters' => [],
        'parent' => 'system.admin',
      ];

      $this->derivatives['toolbar_test_toolbar_items.extra_' . $key] = [
        'title' => $menu_item_data['title'],
        'route_name' => $menu_item_data['route_name'],
        'route_parameters' => $menu_item_data['route_parameters'],
        'parent' => $menu_item_data['parent'],
      ] + $base_plugin_definition;
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
