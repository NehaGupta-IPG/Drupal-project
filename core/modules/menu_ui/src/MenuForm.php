<?php

namespace Drupal\menu_ui;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\system\MenuStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for a menu's own settings (title, name, description, language).
 *
 * The menu's link list lives on its own "Links" page
 * (\Drupal\menu_ui\Form\MenuLinksOverviewForm), so that menu settings and link
 * management are not crammed into one screen. See
 * https://www.drupal.org/project/drupal/issues/2520232.
 *
 * @internal
 */
class MenuForm extends EntityForm {

  /**
   * The menu link manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * Constructs a MenuForm object.
   *
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager
   *   The menu link manager.
   */
  public function __construct(MenuLinkManagerInterface $menu_link_manager) {
    $this->menuLinkManager = $menu_link_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.menu.link'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $menu = $this->entity;

    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('Edit menu %label', ['%label' => $menu->label()]);
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $menu->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Menu name'),
      '#default_value' => $menu->id(),
      '#maxlength' => MenuStorage::MAX_ID_LENGTH,
      '#description' => $this->t('A unique name to construct the URL for the menu. It must only contain lowercase letters, numbers and hyphens.'),
      '#machine_name' => [
        'exists' => [$this, 'menuNameExists'],
        'source' => ['label'],
        'replace_pattern' => '[^a-z0-9-]+',
        'replace' => '-',
      ],
      // A menu's machine name cannot be changed.
      '#disabled' => !$menu->isNew() || $menu->isLocked(),
    ];
    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Administrative summary'),
      '#maxlength' => 512,
      '#default_value' => $menu->getDescription(),
    ];

    $form['langcode'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Menu language'),
      '#languages' => LanguageInterface::STATE_ALL,
      '#default_value' => $menu->language()->getId(),
    ];

    return parent::form($form, $form_state);
  }

  /**
   * Returns whether a menu name already exists.
   *
   * @param string $value
   *   The name of the menu.
   *
   * @return bool
   *   Returns TRUE if the menu already exists, FALSE otherwise.
   */
  public function menuNameExists($value) {
    // Check first to see if a menu with this ID exists.
    if ($this->entityTypeManager->getStorage('menu')->getQuery()->condition('id', $value)->range(0, 1)->count()->execute()) {
      return TRUE;
    }

    // Check for a link assigned to this menu.
    return $this->menuLinkManager->menuNameInUse($value);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $menu = $this->entity;
    $status = $menu->save();
    $edit_link = $this->entity->toLink($this->t('Edit'), 'edit-form')->toString();
    if ($status == SAVED_UPDATED) {
      $this->messenger()->addStatus($this->t('Menu %label has been updated.', ['%label' => $menu->label()]));
      $this->logger('menu')->notice('Menu %label has been updated.', ['%label' => $menu->label(), 'link' => $edit_link]);
    }
    else {
      $this->messenger()->addStatus($this->t('Menu %label has been added.', ['%label' => $menu->label()]));
      $this->logger('menu')->notice('Menu %label has been added.', ['%label' => $menu->label(), 'link' => $edit_link]);
    }

    $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
  }

}
