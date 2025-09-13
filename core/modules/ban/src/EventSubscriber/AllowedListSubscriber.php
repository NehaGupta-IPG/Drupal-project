<?php

declare(strict_types=1);

namespace Drupal\ban\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\ban\Event\BanEvent;

/**
 * Vetoes bans for IPs present on the allowed list.
 */
final class AllowedListSubscriber implements EventSubscriberInterface {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      BanEvent::EVENT_NAME => 'checkAllowedList',
    ];
  }

  /**
   * Sets $event->isBanned to FALSE if the IP is on the allowed list.
   */
  public function checkAllowedList(BanEvent $event): void {
    if ($this->isAllowed($event->ip)) {
      $event->isBanned = FALSE;
    }
  }

  /**
   * Checks whether an Allowed IP config entity exists for $ip.
   */
  protected function isAllowed(string $ip): bool {
    $ids = $this->entityTypeManager
      ->getStorage('ban_allowed_ip')
      ->getQuery()
      ->condition('allowedIp', $ip)
      ->execute();

    return (bool) $ids;
  }

}
