<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Plugin\TreegridColumn;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_link_content\MenuLinkContentStorageInterface;
use Drupal\menu_tree_ui\MenuLinkMovability;
use Drupal\menu_tree_ui\MenuTreeRowContext;
use Drupal\menu_tree_ui\MenuTreeUiConstants;
use Drupal\treegrid\Attribute\TreegridColumn;
use Drupal\treegrid\Plugin\TreegridColumn\TreegridColumnBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Column plugin for the menu link 'enabled' flag.
 *
 * "Enabled" controls whether a menu link appears in rendered menus. This column
 * exposes it as an inline checkbox so editors can disable a link without
 * opening the full edit form. It applies to every link, leaf or parent.
 *
 * The checkbox is interactive for any movable link — a menu_link_content
 * entity or a static MenuLinkDefault link overridden via
 * StaticMenuLinkOverrides — and disabled for links backed by a bespoke plugin
 * class that supports neither.
 *
 * @internal
 */
#[TreegridColumn(
  id: 'enabled',
  title: new TranslatableMarkup('Enabled'),
  weight: 5,
)]
final class EnabledColumn extends TreegridColumnBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly MenuLinkManagerInterface $menuLinkManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('plugin.manager.menu.link'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildCell(object $context): array {
    assert($context instanceof MenuTreeRowContext);
    $link = $context->element->link;

    return [
      '#theme' => 'treegrid_cell_checkbox',
      '#value' => $link->isEnabled(),
      '#label' => (string) $this->t('Enabled'),
      '#interactive' => MenuLinkMovability::isMovable($link),
      '#column_id' => $this->getPluginId(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function handleUpdate(string $row_id, mixed $value): bool {
    if (!$this->menuLinkManager->hasDefinition($row_id)) {
      return FALSE;
    }

    if (MenuTreeUiConstants::isContentLink($row_id)) {
      return $this->handleContentLinkUpdate($row_id, (bool) $value);
    }

    $link = $this->menuLinkManager->createInstance($row_id);
    if (!MenuLinkMovability::isMovable($link)) {
      return FALSE;
    }

    $this->menuLinkManager->updateDefinition($row_id, ['enabled' => (bool) $value]);
    return TRUE;
  }

  /**
   * Applies an enabled-flag update to a menu_link_content-backed link.
   *
   * @param string $link_id
   *   The "menu_link_content:{uuid}" plugin ID.
   * @param bool $value
   *   The new enabled value.
   *
   * @return bool
   *   TRUE if the update was applied, FALSE if the entity does not exist, is
   *   not editable by the current user, or has a pending revision.
   */
  private function handleContentLinkUpdate(string $link_id, bool $value): bool {
    $uuid = MenuTreeUiConstants::uuidFromPluginId($link_id);
    /** @var \Drupal\menu_link_content\MenuLinkContentStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('uuid', $uuid)
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return FALSE;
    }

    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $entity */
    $entity = $storage->load(reset($ids));
    if (!$entity instanceof MenuLinkContentInterface || !$entity->access('update')) {
      return FALSE;
    }

    assert($storage instanceof MenuLinkContentStorageInterface);
    if (in_array($entity->id(), $storage->getMenuLinkIdsWithPendingRevisions(), TRUE)) {
      return FALSE;
    }

    $entity->set('enabled', $value);
    $entity->save();
    return TRUE;
  }

}
