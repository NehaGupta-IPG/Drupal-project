<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Plugin\TreegridColumn;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuTreeStorage;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_tree_ui\MenuTreeRowContext;
use Drupal\treegrid\Attribute\TreegridColumn;
use Drupal\treegrid\Plugin\TreegridColumn\TreegridColumnBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Column plugin for a menu link's row operations (Edit, Delete, Add child, …).
 *
 * The cell is an 'operations' render element, so the active admin theme draws
 * the dropbutton exactly as it does on the classic menu admin, and its
 * libraries and behaviors reach rows loaded later. The column is read-only and
 * last.
 *
 * @internal
 */
#[TreegridColumn(
  id: 'operations',
  title: new TranslatableMarkup('Operations'),
  weight: 100,
)]
final class OperationsColumn extends TreegridColumnBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly ModuleHandlerInterface $moduleHandler,
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
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildCell(object $context): array {
    assert($context instanceof MenuTreeRowContext);
    $links = $this->buildOperationsLinks($context->element, $context->entity);
    if (!$links) {
      return [];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['menu-tree-ui-row__operations'],
        // The treegrid roving tab order includes this container's focusable
        // operation links for the active row.
        'data-treegrid-control' => '',
      ],
      'operations' => [
        '#type' => 'operations',
        '#links' => $links,
      ],
    ];
  }

  /**
   * Assembles the operations list for a row.
   *
   * Returns a keyed array of ['title', 'url', 'weight'] entries — the same
   * shape as hook_entity_operation(). Invokes
   * hook_menu_tree_ui_row_operations_alter() so third-party modules can add,
   * modify, or remove operations.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement $element
   *   The tree element whose operations to build.
   * @param \Drupal\menu_link_content\MenuLinkContentInterface|null $entity
   *   The row's pre-loaded content entity, or NULL for a static/yml link.
   *
   * @return array
   *   Operations keyed by ID; each has 'title', 'url' and 'weight'.
   */
  private function buildOperationsLinks(MenuLinkTreeElement $element, ?MenuLinkContentInterface $entity): array {
    $link = $element->link;
    $operations = [];

    // A content link whose entity was deleted can linger in the menu tree as an
    // orphaned plugin definition; resolving its edit route loads the missing
    // entity and throws. Skip the edit operation for such links rather than
    // crashing the whole tree — the row still renders from the cached
    // definition, and Delete is already omitted (the entity is not loaded).
    try {
      $edit_route = $link->getEditRoute();
    }
    catch (PluginException) {
      $edit_route = NULL;
    }
    if ($edit_route) {
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'url' => $edit_route,
        'weight' => 0,
      ];
    }

    if ($entity instanceof MenuLinkContentInterface) {
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'url' => $entity->toUrl('delete-form'),
        'weight' => 100,
      ];
    }

    // "Add child" mirrors menu_ui's MenuForm: a child sits one level deeper, so
    // offer it only while that stays within the materialized-path depth cap.
    if ($element->depth < MenuTreeStorage::MAX_DEPTH) {
      $operations['add-child'] = [
        'title' => $this->t('Add child'),
        'url' => Url::fromRoute(
          'entity.menu.add_link_form',
          ['menu' => $link->getMenuName()],
          ['query' => ['parent' => $link->getPluginId()]],
        ),
        'weight' => 20,
      ];
    }

    $this->moduleHandler->alter('menu_tree_ui_row_operations', $operations, $element);

    // Every row action returns to this menu-tree page, regardless of how deep
    // the row is nested. Operation URLs otherwise inherit the *current request*
    // as their destination: harmless for the first page, but a row from the
    // children endpoint would send the user back to the rows response after
    // saving. Setting the destination explicitly keeps all levels consistent.
    $destination = Url::fromRoute('menu_tree_ui.edit', ['menu' => $link->getMenuName()])->toString();
    foreach ($operations as &$operation) {
      if (($operation['url'] ?? NULL) instanceof Url) {
        $operation['url']->setOption('query', ['destination' => $destination] + ($operation['url']->getOption('query') ?? []));
      }
    }
    unset($operation);

    uasort($operations, static fn(array $a, array $b): int => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
    return $operations;
  }

}
