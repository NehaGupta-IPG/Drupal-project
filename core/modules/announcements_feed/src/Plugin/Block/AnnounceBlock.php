<?php

declare(strict_types=1);

namespace Drupal\announcements_feed\Plugin\Block;

use Drupal\announcements_feed\AnnounceRenderer;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Announcements Feed' block.
 *
 * @internal
 */
#[Block(
  id: 'announce_block',
  admin_label: new TranslatableMarkup('Announcements Feed')),
]
class AnnounceBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected AnnounceRenderer $announceRenderer) {
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
      $container->get('announcements_feed.renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'access announcements');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return $this->announceRenderer->render();
  }

}
