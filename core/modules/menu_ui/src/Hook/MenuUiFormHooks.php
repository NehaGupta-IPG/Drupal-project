<?php

namespace Drupal\menu_ui\Hook;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use Drupal\system\MenuInterface;

/**
 * Hook form implementations for menu_ui.
 */
class MenuUiFormHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new MenuUiHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Implements hook_form_FORM_ID_alter() for \Drupal\node\NodeTypeForm.
   *
   * Adds menu options to the node type form.
   *
   * @see NodeTypeForm::form()
   * @see menu_ui_form_node_type_form_builder()
   */
  #[Hook('form_node_type_form_alter')]
  public function formNodeTypeFormAlter(&$form, FormStateInterface $form_state) : void {
    /** @var \Drupal\Core\Menu\MenuParentFormSelectorInterface $menu_parent_selector */
    $menu_parent_selector = \Drupal::service('menu.parent_form_selector');
    $menu_options = array_map(function (MenuInterface $menu) {
      return $menu->label();
    }, Menu::loadMultiple());
    asort($menu_options);
    /** @var \Drupal\node\NodeTypeInterface $type */
    $type = $form_state->getFormObject()->getEntity();
    $form['menu'] = [
      '#type' => 'details',
      '#title' => $this->t('Menu settings'),
      '#attached' => [
        'library' => [
          'menu_ui/drupal.menu_ui.admin',
        ],
      ],
      '#group' => 'additional_settings',
    ];
    $form['menu']['menu_options'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available menus'),
      '#default_value' => $type->getThirdPartySetting('menu_ui', 'available_menus', [
        'main',
      ]),
      '#options' => $menu_options,
      '#description' => $this->t('Content of this type can be placed in the selected menus.'),
    ];
    // @todo See if we can avoid pre-loading all options by changing the form or
    //   using a #process callback. https://www.drupal.org/node/2310319 To avoid
    //   an 'illegal option' error after saving the form we have to load all
    //   available menu parents. Otherwise, it is not possible to dynamically
    //   add options to the list using ajax.
    $options_cacheability = new CacheableMetadata();
    $options = $menu_parent_selector->getParentSelectOptions('', NULL, $options_cacheability);
    $form['menu']['menu_parent'] = [
      '#type' => 'select',
      '#title' => $this->t('Default parent link'),
      '#default_value' => $type->getThirdPartySetting('menu_ui', 'parent', 'main:'),
      '#options' => $options,
      '#description' => $this->t('Choose the menu link to be the default parent for a new link in the content authoring form.'),
      '#attributes' => [
        'class' => [
          'menu-title-select',
        ],
      ],
    ];
    $options_cacheability->applyTo($form['menu']['menu_parent']);
    $form['#validate'][] = 'menu_ui_form_node_type_form_validate';
    $form['#entity_builders'][] = 'menu_ui_form_node_type_form_builder';
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for \Drupal\node\NodeForm.
   *
   * Adds menu item fields to the node form.
   *
   * @see menu_ui_form_node_form_submit()
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(&$form, FormStateInterface $form_state) : void {
    // Generate a list of possible parents (not including this link or
    // descendants).
    // @todo This must be handled in a #process handler.
    $node = $form_state->getFormObject()->getEntity();
    $defaults = menu_ui_get_menu_link_defaults($node);
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = $node->type->entity;
    /** @var \Drupal\Core\Menu\MenuParentFormSelectorInterface $menu_parent_selector */
    $menu_parent_selector = \Drupal::service('menu.parent_form_selector');
    $type_menus_ids = $node_type->getThirdPartySetting('menu_ui', 'available_menus', ['main']);
    if (empty($type_menus_ids)) {
      return;
    }
    /** @var \Drupal\system\MenuInterface[] $type_menus */
    $type_menus = Menu::loadMultiple($type_menus_ids);
    $available_menus = [];
    foreach ($type_menus as $menu) {
      $available_menus[$menu->id()] = $menu->label();
    }
    if ($defaults['id']) {
      $default = $defaults['menu_name'] . ':' . $defaults['parent'];
    }
    else {
      $default = $node_type->getThirdPartySetting('menu_ui', 'parent', 'main:');
    }
    $parent_element = $menu_parent_selector->parentSelectElement($default, $defaults['id'], $available_menus);
    // If no possible parent menu items were found, there is nothing to display.
    if (empty($parent_element)) {
      return;
    }
    $form['menu'] = [
      '#type' => 'details',
      '#title' => $this->t('Menu settings'),
      '#access' => $this->getMenuLinkContentAccess($defaults),
      '#open' => (bool) $defaults['id'],
      '#group' => 'advanced',
      '#attached' => [
        'library' => [
          'menu_ui/drupal.menu_ui',
        ],
      ],
      '#tree' => TRUE,
      '#weight' => -2,
      '#attributes' => [
        'class' => [
          'menu-link-form',
        ],
      ],
    ];
    $form['menu']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Provide a menu link'),
      '#default_value' => (int) (bool) $defaults['id'],
    ];
    $form['menu']['link'] = [
      '#type' => 'container',
      '#parents' => [
        'menu',
      ],
      '#states' => [
        'invisible' => [
          'input[name="menu[enabled]"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];
    // Populate the element with the link data.
    foreach (['id', 'entity_id'] as $key) {
      $form['menu']['link'][$key] = ['#type' => 'value', '#value' => $defaults[$key]];
    }
    $form['menu']['link']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Menu link title'),
      '#default_value' => $defaults['title'],
      '#maxlength' => $defaults['title_max_length'],
    ];
    $form['menu']['link']['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#default_value' => $defaults['description'],
      '#description' => $this->t('Shown when hovering over the menu link.'),
      '#maxlength' => $defaults['description_max_length'],
    ];
    $form['menu']['link']['menu_parent'] = $parent_element;
    $form['menu']['link']['menu_parent']['#title'] = $this->t('Parent link');
    $form['menu']['link']['menu_parent']['#attributes']['class'][] = 'menu-parent-select';
    $form['menu']['link']['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#default_value' => $defaults['weight'],
      '#description' => $this->t('Menu links with lower weights are displayed before links with higher weights.'),
    ];
    foreach (array_keys($form['actions']) as $action) {
      if ($action != 'preview' && isset($form['actions'][$action]['#type']) && $form['actions'][$action]['#type'] === 'submit') {
        $form['actions'][$action]['#submit'][] = 'menu_ui_form_node_form_submit';
      }
    }
    $form['#entity_builders'][] = 'menu_ui_node_builder';
  }

  /**
   * Check if user is allowed to use the menu link subform.
   *
   * @param array $defaults
   *   An array that contains default values for the menu link form.
   *
   * @see menu_ui_get_menu_link_defaults()
   */
  protected function getMenuLinkContentAccess(array $defaults): AccessResultInterface {
    if (!empty($defaults['entity_id'])) {
      $entity = MenuLinkContent::load($defaults['entity_id']);

      // The form can be used to edit or delete the menu link.
      return $entity->access('update', NULL, TRUE)->andIf($entity->access('delete', NULL, TRUE));
    }
    else {
      // If the node has no corresponding menu link, users needs to permission to create one.
      return $this->entityTypeManager->getAccessControlHandler('menu_link_content')->createAccess(NULL, NULL, [], TRUE);
    }
  }

}
