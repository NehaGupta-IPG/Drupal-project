<?php

namespace Drupal\Core\ParamConverter;

use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Symfony\Component\Routing\Route;

/**
 * Parameter converter for upcasting entity ids to full objects.
 */
class MenuLinkPluginConverter implements ParamConverterInterface {

  public function __construct(protected \Closure $menuLinkManagerClosure) {
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if ($value) {
      try {
        return ($this->menuLinkManagerClosure)()->createInstance($value);
      }
      catch (PluginException) {
        // Suppress the error.
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return (!empty($definition['type']) && $definition['type'] === 'menu_link_plugin');
  }

}
