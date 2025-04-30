<?php

namespace Drupal\menu_ui\Hook;

use Drupal\block\BlockInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\system\Entity\Menu;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for menu_ui.
 */
class MenuUiHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.menu_ui':
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Menu UI module provides an interface for managing menus. A menu is a hierarchical collection of links, which can be within or external to the site, generally used for navigation. For more information, see the <a href=":menu">online documentation for the Menu UI module</a>.', [
          ':menu' => 'https://www.drupal.org/docs/core-modules-and-themes/core-modules/menu-ui-module',
        ]) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Managing menus') . '</dt>';
        $output .= '<dd>' . $this->t('Users with the <em>Administer menus and menu links</em> permission can add, edit, and delete custom menus on the <a href=":menu">Menus page</a>. Custom menus can be special site menus, menus of external links, or any combination of internal and external links. You may create an unlimited number of additional menus, each of which will automatically have an associated block (if you have the <a href=":block_help">Block module</a> installed). By selecting <em>Edit menu</em>, you can add, edit, or delete links for a given menu. The links listing page provides a drag-and-drop interface for controlling the order of links, and creating a hierarchy within the menu.', [
          ':block_help' => \Drupal::moduleHandler()->moduleExists('block') ? Url::fromRoute('help.page', [
            'name' => 'block',
          ])->toString() : '#',
          ':menu' => Url::fromRoute('entity.menu.collection')->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Displaying menus') . '</dt>';
        $output .= '<dd>' . $this->t('If you have the Block module installed, then each menu that you create is rendered in a block that you enable and position on the <a href=":blocks">Block layout page</a>. In some <a href=":themes">themes</a>, the main menu and possibly the secondary menu will be output automatically; you may be able to disable this behavior on the <a href=":themes">theme\'s settings page</a>.', [
          ':blocks' => \Drupal::moduleHandler()->moduleExists('block') ? Url::fromRoute('block.admin_display')->toString() : '#',
          ':themes' => Url::fromRoute('system.themes_page')->toString(),
          ':theme_settings' => Url::fromRoute('system.theme_settings')->toString(),
        ]) . '</dd>';
        $output .= '</dl>';
        return $output;
    }
    if ($route_name == 'entity.menu.add_form' && \Drupal::moduleHandler()->moduleExists('block') && \Drupal::currentUser()->hasPermission('administer blocks')) {
      return '<p>' . $this->t('You can enable the newly-created block for this menu on the <a href=":blocks">Block layout page</a>.', [':blocks' => Url::fromRoute('block.admin_display')->toString()]) . '</p>';
    }
    elseif ($route_name == 'entity.menu.collection' && \Drupal::moduleHandler()->moduleExists('block') && \Drupal::currentUser()->hasPermission('administer blocks')) {
      return '<p>' . $this->t('Each menu has a corresponding block that is managed on the <a href=":blocks">Block layout page</a>.', [':blocks' => Url::fromRoute('block.admin_display')->toString()]) . '</p>';
    }
    return NULL;
  }

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    $entity_types['menu']->setFormClass('add', 'Drupal\menu_ui\MenuForm')->setFormClass('edit', 'Drupal\menu_ui\MenuForm')->setFormClass('delete', 'Drupal\menu_ui\Form\MenuDeleteForm')->setListBuilderClass('Drupal\menu_ui\MenuListBuilder')->setLinkTemplate('add-form', '/admin/structure/menu/add')->setLinkTemplate('delete-form', '/admin/structure/menu/manage/{menu}/delete')->setLinkTemplate('edit-form', '/admin/structure/menu/manage/{menu}')->setLinkTemplate('add-link-form', '/admin/structure/menu/manage/{menu}/add')->setLinkTemplate('collection', '/admin/structure/menu');
    if (isset($entity_types['node'])) {
      $entity_types['node']->addConstraint('MenuSettings', []);
    }
  }

  /**
   * Implements hook_block_view_BASE_BLOCK_ID_alter() for 'system_menu_block'.
   */
  #[Hook('block_view_system_menu_block_alter')]
  public function blockViewSystemMenuBlockAlter(array &$build, BlockPluginInterface $block): void {
    if ($block->getBaseId() == 'system_menu_block') {
      $menu_name = $block->getDerivativeId();
      $build['#contextual_links']['menu'] = ['route_parameters' => ['menu' => $menu_name]];
    }
  }

  /**
   * Implements hook_system_breadcrumb_alter().
   */
  #[Hook('system_breadcrumb_alter')]
  public function systemBreadcrumbAlter(Breadcrumb $breadcrumb, RouteMatchInterface $route_match, array $context): void {
    // Custom breadcrumb behavior for editing menu links, we append a link to
    // the menu in which the link is found.
    if ($route_match->getRouteName() == 'menu_ui.link_edit' && ($menu_link = $route_match->getParameter('menu_link_plugin'))) {
      if ($menu_link instanceof MenuLinkInterface) {
        // Add a link to the menu admin screen.
        $menu = Menu::load($menu_link->getMenuName());
        $breadcrumb->addLink(Link::createFromRoute($menu->label(), 'entity.menu.edit_form', ['menu' => $menu->id()]));
      }
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() : array {
    return ['menu_link_form' => ['render element' => 'form']];
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity) : array {
    $operations = [];
    if ($entity instanceof BlockInterface) {
      $plugin = $entity->getPlugin();
      if ($plugin->getBaseId() === 'system_menu_block') {
        $menu = Menu::load($plugin->getDerivativeId());
        if ($menu && $menu->access('edit')) {
          $operations['menu-edit'] = ['title' => $this->t('Edit menu'), 'url' => $menu->toUrl('edit-form'), 'weight' => 50];
        }
      }
    }
    return $operations;
  }

}
