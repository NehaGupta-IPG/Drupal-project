<?php

declare(strict_types=1);

namespace Drupal\menu_ui;

use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Warns the user about menu item changes after an entity is deleted.
 *
 * @internal
 */
final class EntityDeleteMenuDescendants {

  use StringTranslationTrait;

  /**
   * Constructs a new EntityDeleteMenuDescendants.
   */
  public function __construct(
    private readonly MenuLinkTreeInterface $menuLinkTree,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly MessengerInterface $messenger,
    private readonly RendererInterface $renderer,
    TranslationInterface $stringTranslation,
  ) {
    $this->setStringTranslation($stringTranslation);
  }

  /**
   * Implements hook_form_alter().
   */
  public function formAlter(&$form, FormStateInterface $form_state, $form_id): void {
    $formObject = $form_state->getFormObject();
    if (!$formObject instanceof ContentEntityFormInterface || $formObject->getOperation() !== 'delete') {
      return;
    }

    $entity = $formObject->getEntity();
    $tree = $this->getDescendantTree($entity);
    if (count($tree) === 0) {
      return;
    }

    $suggested_action = "";
    if ($entity->getEntityTypeId() !== 'menu_link_content') {
      $menu = current($tree)->link;
      $suggested_action = $this->t("You may want to move these menu items to another menu before deleting this @singular_label. <a target='_blank' href=':menu_link_page'>Got to menu page</a>", [
        '@singular_label' => $entity->getEntityType()->getSingularLabel(),
        ':menu_link_page' => Url::fromRoute('entity.menu.edit_form', ['menu' => $menu->getMenuName()])->toString(),
      ]);
    }

    $form['menu_ui_affected_descendants'] = [
      '#type' => 'inline_template',
      '#template' => '<p>{{ message }}</p>{{ menu }}<p>{{ suggested_action }}</p>',
      '#context' => [
        'message' => [
          '#markup' => $this->formatPlural(
            count($tree),
            'Deleting this @singular_label will make this @count child menu item top level:',
            'Deleting this @singular_label will make these @count child menu items top level:',
            ['@singular_label' => $entity->getEntityType()->getSingularLabel()],
          ),
        ],
        'menu' => $this->menuLinkTree->build($tree),
        'suggested_action' => $suggested_action,
      ],
    ];

    // Execute submit handler before the entity is saved, so we have an
    // opportunity to get the menu items before the menu is modified.
    array_unshift(
      $form['actions']['submit']['#submit'],
      [$this, 'submitCallback'],
    );
  }

  public function submitCallback(array $form, FormStateInterface $form_state): void {
    $entity = $form_state->getFormObject()->getEntity();
    $tree = $this->getDescendantTree($entity);
    if (count($tree) === 0) {
      return;
    }

    $message = [
      '#type' => 'inline_template',
      '#template' => '<p>{{ message }}</p>{{ menu }}',
      '#context' => [
        'message' => [
          '#markup' => $this->formatPlural(
            count($tree),
            'The menu item for @entity had @count child menu item. This child menu item moved to the same level as the now-deleted menu item. The affected menu item is:',
            'The menu items for @entity had @count children menu items. These children menu items moved to the same level as the now-deleted menu item. Affected menu items include:',
            ['@entity' => $entity->label()]),
        ],
        'menu' => $this->menuLinkTree->build($tree),
      ],
    ];
    $message = $this->renderer->render($message);
    $this->messenger->addWarning($message);
  }

  /**
   * Gets the menu link tree below the menu link representing this entity.
   *
   * The mechanism for matching an entity to a Menu Link Content is similar to
   * menu_ui_get_menu_link_defaults().
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The descendant menu link tree.
   */
  private function getDescendantTree(EntityInterface $entity): array {
    if ($entity instanceof MenuLinkContentInterface) {
      // Deleting a menu link content entity directly.
      $menuLink = $entity;
    }
    else {
      $menuLinkContentStorage = $this->entityTypeManager->getStorage('menu_link_content');
      $query = $menuLinkContentStorage
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('link.uri', 'entity:' . $entity->getEntityTypeId() . '/' . $entity->id())
        ->sort('id', 'ASC')
        ->range(0, 1);
      $ids = $query->execute();
      $id = reset($ids);
      /** @var \Drupal\menu_link_content\Entity\MenuLinkContent|null $menuLink */
      $menuLink = $id !== FALSE ? $menuLinkContentStorage->load($id) : NULL;
      if ($menuLink === NULL) {
        return [];
      }

      $menuLink = $this->entityRepository->getTranslationFromContext($menuLink);
    }

    $parameters = (new MenuTreeParameters())
      ->setRoot($menuLink->getPluginId())
      ->excludeRoot()
      ->setMinDepth(1)
      ->setMaxDepth(1);
    return $this->menuLinkTree->transform(
      tree: $this->menuLinkTree->load($menuLink->getMenuName(), $parameters),
      manipulators: [
        ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ],
    );
  }

}
