<?php

namespace Drupal\menu_ui\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigManagerInterface;

/**
 * Defines a confirmation form for deletion of a custom menu.
 *
 * @internal
 */
class MenuDeleteForm extends EntityDeleteForm {

  /**
   * The menu link manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The config manager service.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * Constructs a new MenuDeleteForm.
   *
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager
   *   The menu link manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The config manager.
   */
  public function __construct(MenuLinkManagerInterface $menu_link_manager, Connection $connection, ConfigManagerInterface $config_manager) {
    $this->menuLinkManager = $menu_link_manager;
    $this->connection = $connection;
    $this->configManager = $config_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.menu.link'),
      $container->get('database'),
      $container->get('config.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $caption = '';
    $num_links = $this->menuLinkManager->countMenuLinks($this->entity->id());
    if ($num_links) {
      $caption .= '<p>' . $this->formatPlural($num_links, '<strong>Warning:</strong> There is currently 1 menu link in %title. It will be deleted (system-defined links will be reset).', '<strong>Warning:</strong> There are currently @count menu links in %title. They will be deleted (system-defined links will be reset).', ['%title' => $this->entity->label()]) . '</p>';
    }
    $caption .= '<p>' . $this->t('This action cannot be undone.') . '</p>';
    return $caption;
  }

  /**
   * {@inheritdoc}
   */
  protected function logDeletionMessage() {
    $this->logger('menu')->notice('Deleted custom menu %title and all its menu links.', ['%title' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Locked menus may not be deleted.
    if ($this->entity->isLocked()) {
      return;
    }
    $menu_name = $this->entity->id();
    $dependents = $this->configManager->findConfigEntityDependencies('config', ["system.menu.$menu_name"]);
    $view_dependencies = [];
    foreach ($dependents as $config_name => $dependency) {
      if (str_starts_with($config_name, 'views.view.')) {
        $view_dependencies[] = $config_name;
      }
    }
    if (!empty($view_dependencies)) {
      $this->messenger()->addError($this->t(
        'Cannot delete the %menu menu because it is used by the following Views: %views. Remove the menu link from those Views first.',
        [
          '%menu' => $menu_name,
          '%views' => implode(', ', $view_dependencies),
        ]
      ));
      return;
    }

    // Delete all links to the overview page for this menu.
    // @todo Add a more generic helper function to the menu link plugin
    //   manager to remove links to an entity or other ID used as a route
    //   parameter that is being removed. Also, consider moving this to
    //   menu_ui.module as part of a generic response to entity deletion.
    //   https://www.drupal.org/node/2310329
    $menu_links = $this->menuLinkManager->loadLinksByRoute('entity.menu.edit_form', ['menu' => $this->entity->id()], TRUE);
    foreach ($menu_links as $id => $link) {
      $this->menuLinkManager->removeDefinition($id);
    }

    parent::submitForm($form, $form_state);
  }

}
