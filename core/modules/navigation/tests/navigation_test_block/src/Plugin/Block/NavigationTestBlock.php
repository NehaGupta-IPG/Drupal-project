<?php

declare(strict_types=1);

namespace Drupal\navigation_test_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\navigation\Attribute\AllowInNavigation;

/**
 * Defines a dummy navigation block for testing purposes.
 *
 * This module does not depend on navigation, so the attribute is only applied
 * once navigation is installed.
 *
 * @internal
 */
#[Block(
  id: 'navigation_test',
  admin_label: new TranslatableMarkup('Navigation Test'),
)]
#[AllowInNavigation]
final class NavigationTestBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->configuration;
    $build = [];

    return $build + [
      '#title' => $config['label'],
      '#theme' => 'navigation_menu',
      '#menu_name' => 'test',
      '#items' => [
        [
          'title' => 'Test Navigation Block',
          'class' => 'test-block',
          'icon' => [
            'icon_id' => 'test-block',
          ],
          'url' => Url::fromRoute('<front>'),
        ],
      ],
    ];
  }

}
