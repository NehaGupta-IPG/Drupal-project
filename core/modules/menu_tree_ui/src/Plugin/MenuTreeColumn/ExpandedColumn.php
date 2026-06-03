<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Plugin\MenuTreeColumn;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_tree_ui\Attribute\MenuTreeColumn;
use Drupal\menu_tree_ui\MenuTreeUiConstants;

/**
 * Column plugin for the menu_link_content 'expanded' field.
 *
 * "Expanded" controls whether a menu item's children are shown pre-expanded
 * in navigation blocks on the front end — it is NOT the admin tree expansion
 * state. This column exposes it as an inline toggle so editors never need to
 * open the full edit form just to flip this flag.
 *
 * Static/yml-defined links do not have a writeable expanded property; for those
 * the cell is rendered as a read-only indicator.
 */
#[MenuTreeColumn(
  id: 'expanded',
  title: new TranslatableMarkup('Expanded'),
  description: new TranslatableMarkup("Whether this item's children are shown expanded in navigation blocks."),
  weight: 10,
)]
final class ExpandedColumn extends MenuTreeColumnBase {

  /**
   * {@inheritdoc}
   */
  public function getCellData(MenuLinkTreeElement $element): array {
    if (!$element->hasChildren) {
      return [];
    }

    $link = $element->link;
    $plugin_id = $link->getPluginId();
    $definition = $link->getPluginDefinition();

    return [
      'type' => 'toggle',
      'value' => !empty($definition['expanded']),
      'label' => (string) $this->t('Show as expanded'),
      'interactive' => str_starts_with($plugin_id, MenuTreeUiConstants::CONTENT_PLUGIN_PREFIX),
      'link_id' => $plugin_id,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function handleUpdate(EntityInterface $entity, mixed $value): bool {
    if (!$entity instanceof MenuLinkContentInterface) {
      return FALSE;
    }
    $entity->set('expanded', (bool) $value);
    $entity->save();
    return TRUE;
  }

}
