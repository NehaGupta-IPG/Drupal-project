<?php

namespace Drupal\user\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class UserPageMenuLink extends MenuLinkDefault {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, StaticMenuLinkOverridesInterface $static_override, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);

    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu_link.static.overrides'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    $title = parent::getTitle();

    if ($this->requestStack->getCurrentRequest()->attributes->get('_menu_admin', FALSE)) {
      $title .= ' (' . $this->t('logged in users only') . ')';
    }
    return $title;
  }

}
