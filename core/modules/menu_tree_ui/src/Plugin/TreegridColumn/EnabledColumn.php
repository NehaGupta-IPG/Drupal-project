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
 * Column plugin for the menu_link_content 'enabled' field.
 *
 * "Enabled" controls whether a menu link appears in rendered menus. This column
 * exposes it as an inline toggle so editors can disable a link without opening
 * the full edit form. Unlike "Expanded", it applies to every link, leaf or
 * parent, and sorts ahead of "Expanded".
 *
 * Static/yml-defined links have no writable enabled property here; for those
 * the cell is rendered as a read-only indicator.
 */
#[TreegridColumn(
  id: 'enabled',
  title: new TranslatableMarkup('Enabled'),
  weight: 5,
)]
final class EnabledColumn extends TreegridColumnBase {

  /**
   * {@inheritdoc}
   */
  public function getCellData(object $context): array {
    assert($context instanceof MenuLinkTreeElement);
    $link = $context->link;
    $plugin_id = $link->getPluginId();

    return [
      'type' => 'toggle',
      'value' => $link->isEnabled(),
      'label' => (string) $this->t('Enabled'),
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
    $entity->set('enabled', (bool) $value);
    $entity->save();
    return TRUE;
  }

}
