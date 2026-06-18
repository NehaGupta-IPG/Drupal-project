<?php

declare(strict_types=1);

namespace Drupal\menu_ui;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;
use Drupal\system\MenuInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds and saves the draggable menu-link overview table for one menu.
 *
 * The classic tabledrag overview used to live inside the combined menu edit
 * form. It is extracted here so that a menu's own settings and its link list
 * can live on separate pages (see
 * https://www.drupal.org/project/drupal/issues/2520232), and so the same table
 * can be reused both by the standalone "Links" page and by the settings_tray
 * off-canvas editor.
 *
 * Callers build the table into a form section, record that section's parents in
 * the 'menu_overview_form_parents' form-state key, and call submit() from their
 * own submit handler.
 */
class MenuOverviewBuilder {

  use StringTranslationTrait;

  /**
   * The flat working copy of the overview tree, built recursively.
   *
   * @var array
   */
  protected array $overviewTreeForm = ['#tree' => TRUE];

  public function __construct(
    protected MenuLinkManagerInterface $menuLinkManager,
    protected MenuLinkTreeInterface $menuTree,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RequestStack $requestStack,
    protected RedirectDestinationInterface $redirectDestination,
    TranslationInterface $string_translation,
  ) {
    $this->setStringTranslation($string_translation);
  }

  /**
   * Builds the menu-link overview section for a menu.
   *
   * @param \Drupal\system\MenuInterface $menu
   *   The menu whose links to list.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the containing form.
   *
   * @return array
   *   A render array with a 'links' tabledrag table, and a 'help' message when
   *   the menu has links with pending revisions. Place it at the parents
   *   recorded in 'menu_overview_form_parents'.
   */
  public function build(MenuInterface $menu, FormStateInterface $form_state): array {
    $section = [];
    $section['#attached']['library'][] = 'menu_ui/drupal.menu_ui.adminforms';

    $tree = $this->menuTree->load($menu->id(), new MenuTreeParameters());

    // We indicate that a menu administrator is running the menu access check.
    $request = $this->requestStack->getCurrentRequest();
    $request->attributes->set('_menu_admin', TRUE);
    $manipulators = [
      // Use a dedicated menu tree access check manipulator as users editing
      // this form, granted with 'administer menu' permission, should be able to
      // access menu links with inaccessible routes. The default menu tree
      // manipulator only allows the access to menu links with accessible
      // routes.
      // @see \Drupal\Core\Menu\DefaultMenuLinkTreeManipulators::checkAccess()
      // @see \Drupal\menu_ui\Menu\MenuUiMenuTreeManipulators::checkAccess()
      ['callable' => 'menu_ui.menu_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    $request->attributes->set('_menu_admin', FALSE);

    // Determine the delta; the number of weights to be made available.
    $count = function (array $tree) {
      $sum = function ($carry, MenuLinkTreeElement $item) {
        return $carry + $item->count();
      };
      return array_reduce($tree, $sum);
    };
    $delta = max($count($tree), 50);

    $section['links'] = [
      '#type' => 'table',
      '#theme' => 'table__menu_overview',
      '#header' => [
        $this->t('Menu link'),
        [
          'data' => $this->t('Enabled'),
          'class' => ['checkbox'],
        ],
        $this->t('Weight'),
        [
          'data' => $this->t('Operations'),
          'colspan' => 3,
        ],
      ],
      '#attributes' => [
        'id' => 'menu-overview',
      ],
      '#tabledrag' => [
        [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'menu-parent',
          'subgroup' => 'menu-parent',
          'source' => 'menu-id',
          'hidden' => TRUE,
          'limit' => $this->menuTree->maxDepth() - 1,
        ],
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'menu-weight',
        ],
      ],
    ];

    $section['links']['#empty'] = $this->t('There are no menu links yet. <a href=":url">Add link</a>.', [
      ':url' => Url::fromRoute('entity.menu.add_link_form', ['menu' => $menu->id()], [
        'query' => ['destination' => $menu->toUrl('links')->toString()],
      ])->toString(),
    ]);
    $links = $this->buildTree($tree, $delta, $menu);

    // Get the menu links which have pending revisions, and disable the
    // tabledrag if there are any.
    $edited_ids = array_filter(array_map(function ($element) {
      return is_array($element) && isset($element['#item']) && $element['#item']->link instanceof MenuLinkContent ? $element['#item']->link->getMetaData()['entity_id'] : NULL;
    }, $links));
    /** @var \Drupal\menu_link_content\MenuLinkContentStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $pending_menu_link_ids = array_intersect($storage->getMenuLinkIdsWithPendingRevisions(), $edited_ids);
    if ($pending_menu_link_ids) {
      $section['help'] = [
        '#type' => 'container',
        'message' => [
          '#markup' => $this->formatPlural(
            count($pending_menu_link_ids),
            '%capital_name contains 1 menu link with pending revisions. Manipulation of a menu tree having links with pending revisions is not supported, but you can re-enable manipulation by getting each menu link to a published state.',
            '%capital_name contains @count menu links with pending revisions. Manipulation of a menu tree having links with pending revisions is not supported, but you can re-enable manipulation by getting each menu link to a published state.',
            [
              '%capital_name' => $menu->label(),
            ]
          ),
        ],
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        '#weight' => -10,
      ];

      unset($section['links']['#tabledrag']);
      unset($section['links']['#header'][2]);
    }

    foreach (Element::children($links) as $id) {
      if (isset($links[$id]['#item'])) {
        $element = $links[$id];

        $is_pending_menu_link = isset($element['#item']->link->getMetaData()['entity_id'])
          && in_array($element['#item']->link->getMetaData()['entity_id'], $pending_menu_link_ids);

        $section['links'][$id]['#item'] = $element['#item'];

        // TableDrag: Mark the table row as draggable.
        $section['links'][$id]['#attributes'] = $element['#attributes'];
        $section['links'][$id]['#attributes']['class'][] = 'draggable';

        if ($is_pending_menu_link) {
          $section['links'][$id]['#attributes']['class'][] = 'color-warning';
          $section['links'][$id]['#attributes']['class'][] = 'menu-link-content--pending-revision';
        }

        // TableDrag: Sort the table row according to its existing/configured
        // weight.
        $section['links'][$id]['#weight'] = $element['#item']->link->getWeight();

        // Add special classes to be used for tabledrag.js.
        $element['parent']['#attributes']['class'] = ['menu-parent'];
        $element['weight']['#attributes']['class'] = ['menu-weight'];
        $element['id']['#attributes']['class'] = ['menu-id'];

        $section['links'][$id]['title'] = [
          [
            '#theme' => 'indentation',
            '#size' => $element['#item']->depth - 1,
          ],
          $element['title'],
        ];
        $section['links'][$id]['enabled'] = $element['enabled'];
        $section['links'][$id]['enabled']['#wrapper_attributes']['class'] = ['checkbox', 'menu-enabled'];

        // Disallow changing the publishing status of a pending revision.
        if ($is_pending_menu_link) {
          $section['links'][$id]['enabled']['#access'] = FALSE;
        }

        if (!$pending_menu_link_ids) {
          $section['links'][$id]['weight'] = $element['weight'];
        }

        // Operations (dropbutton) column.
        $section['links'][$id]['operations'] = $element['operations'];

        $section['links'][$id]['id'] = $element['id'];
        $section['links'][$id]['parent'] = $element['parent'];
      }
    }

    return $section;
  }

  /**
   * Recursive helper for build(): flattens the tree into form rows.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The tree retrieved by \Drupal\Core\Menu\MenuLinkTreeInterface::load().
   * @param int $delta
   *   The default number of menu items used in the menu weight selector is 50.
   * @param \Drupal\system\MenuInterface $menu
   *   The menu being edited, for the per-row "Add child" link.
   *
   * @return array
   *   The overview tree form.
   */
  protected function buildTree(array $tree, int $delta, MenuInterface $menu): array {
    $form = &$this->overviewTreeForm;
    $tree_access_cacheability = new CacheableMetadata();
    foreach ($tree as $element) {
      $tree_access_cacheability = $tree_access_cacheability->merge(CacheableMetadata::createFromObject($element->access));

      // Only render accessible links.
      if (!$element->access->isAllowed()) {
        continue;
      }

      /** @var \Drupal\Core\Menu\MenuLinkInterface $link */
      $link = $element->link;
      if ($link) {
        $id = 'menu_plugin_id:' . $link->getPluginId();
        $form[$id]['#item'] = $element;
        $form[$id]['#attributes'] = $link->isEnabled() ? ['class' => ['menu-enabled']] : ['class' => ['menu-disabled']];
        $form[$id]['title'] = Link::fromTextAndUrl($link->getTitle(), $link->getUrlObject())->toRenderable();
        if (!$link->isEnabled()) {
          $form[$id]['title']['#suffix'] = ' (' . $this->t('disabled') . ')';
        }
        // @todo Remove this in https://www.drupal.org/node/2568785.
        elseif ($id === 'menu_plugin_id:user.logout') {
          $form[$id]['title']['#suffix'] = ' (' . $this->t('<q>Log in</q> for anonymous users') . ')';
        }
        // @todo Remove this in https://www.drupal.org/node/2568785.
        elseif (($url = $link->getUrlObject()) && $url->isRouted() && $url->getRouteName() == 'user.page') {
          $form[$id]['title']['#suffix'] = ' (' . $this->t('logged in users only') . ')';
        }

        $form[$id]['enabled'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable @title menu link', ['@title' => $link->getTitle()]),
          '#title_display' => 'invisible',
          '#default_value' => $link->isEnabled(),
        ];
        $form[$id]['weight'] = [
          '#type' => 'weight',
          '#delta' => $delta,
          '#default_value' => $link->getWeight(),
          '#title' => $this->t('Weight for @title', ['@title' => $link->getTitle()]),
          '#title_display' => 'invisible',
        ];
        $form[$id]['id'] = [
          '#type' => 'hidden',
          '#value' => $link->getPluginId(),
        ];
        $form[$id]['parent'] = [
          '#type' => 'hidden',
          '#default_value' => $link->getParent(),
        ];
        $operations = $link->getOperations();
        if ($element->depth < $this->menuTree->maxDepth()) {
          $add_link_url = Url::fromRoute(
            'entity.menu.add_link_form',
            ['menu' => $menu->id()],
            ['query' => ['parent' => $link->getPluginId()]]
          );
          $operations += [
            'add-child' => [
              'title' => $this->t('Add child'),
              'weight' => 20,
              'url' => $add_link_url,
            ],
          ];
          uasort($operations, [SortArray::class, 'sortByWeightElement']);
        }
        foreach ($operations as $key => $operation) {
          if (!isset($operations[$key]['query'])) {
            // Bring the user back to the menu overview.
            $operations[$key]['query'] = $this->redirectDestination->getAsArray();
          }
        }
        $form[$id]['operations'] = [
          '#type' => 'operations',
          '#links' => $operations,
        ];
      }

      if ($element->subtree) {
        $this->buildTree($element->subtree, $delta, $menu);
      }
    }

    $tree_access_cacheability
      ->merge(CacheableMetadata::createFromRenderArray($form))
      ->applyTo($form);

    return $form;
  }

  /**
   * Saves the submitted link weights, parents and enabled flags.
   *
   * Takes great care to save parent items first, then items underneath them:
   * saving items in the incorrect order can break the tree.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state. Its 'menu_overview_form_parents' key must point to the
   *   section build() was placed in.
   */
  public function submit(array &$form, FormStateInterface $form_state): void {
    // Form API supports constructing and validating self-contained sections
    // within forms, but does not allow to handle the form section's submission
    // equally separated yet. Therefore, we use a $form_state key to point to
    // the parents of the form section.
    $parents = $form_state->get('menu_overview_form_parents');
    $input = NestedArray::getValue($form_state->getUserInput(), $parents);
    $section = &NestedArray::getValue($form, $parents);

    // When dealing with saving menu items, the order in which these items are
    // saved is critical. If a changed child item is saved before its parent,
    // the child item could be saved with an invalid path past its immediate
    // parent. To prevent this, save items in the form in the same order they
    // are sent, ensuring parents are saved first, then their children.
    // See https://www.drupal.org/node/181126#comment-632270.
    $order = is_array($input) ? array_flip(array_keys($input)) : [];
    // Update our original form with the new order.
    $section = array_intersect_key(array_merge($order, $section), $section);

    $fields = ['weight', 'parent', 'enabled'];
    $form_links = $section['links'];
    foreach (Element::children($form_links) as $id) {
      if (isset($form_links[$id]['#item'])) {
        $element = $form_links[$id];
        $updated_values = [];
        // Update any fields that have changed in this menu item.
        foreach ($fields as $field) {
          if (isset($element[$field]['#value']) && $element[$field]['#value'] != $element[$field]['#default_value']) {
            $updated_values[$field] = $element[$field]['#value'];
          }
        }
        if ($updated_values) {
          // Use the ID from the actual plugin instance since the hidden value
          // in the form could be tampered with.
          $this->menuLinkManager->updateDefinition($element['#item']->link->getPluginId(), $updated_values);
        }
      }
    }
  }

}
