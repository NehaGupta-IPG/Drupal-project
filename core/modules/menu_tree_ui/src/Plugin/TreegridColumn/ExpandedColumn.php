<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Plugin\TreegridColumn;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_tree_ui\MenuTreeUiConstants;
use Drupal\treegrid\Attribute\TreegridColumn;
use Drupal\treegrid\Plugin\TreegridColumn\TreegridColumnBase;

/**
 * Column plugin for the menu_link_content 'expanded' field.
 *
 * "Expanded" controls whether a menu item's children are shown pre-expanded
 * in navigation blocks on the front end — it is NOT the admin tree expansion
 * state. This column exposes it as an inline checkbox so editors never need to
 * open the full edit form just to flip this flag.
 *
 * Static/yml-defined links do not have a writable expanded property; for those
 * the checkbox is rendered disabled.
 */
#[TreegridColumn(
  id: 'expanded',
  title: new TranslatableMarkup('Expanded'),
  weight: 10,
)]
final class ExpandedColumn extends TreegridColumnBase {

  /**
   * {@inheritdoc}
   */
  public function getCellData(object $context): array {
    assert($context instanceof MenuLinkTreeElement);
    if (!$context->hasChildren) {
      return [];
    }

    $link = $context->link;
    $plugin_id = $link->getPluginId();
    $definition = $link->getPluginDefinition();

    return [
      'type' => 'checkbox',
      'value' => !empty($definition['expanded']),
      'label' => (string) $this->t('Show as expanded'),
      'interactive' => MenuTreeUiConstants::isContentLink($plugin_id),
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
