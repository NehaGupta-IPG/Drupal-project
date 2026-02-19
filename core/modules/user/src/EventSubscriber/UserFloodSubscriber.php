<?php

namespace Drupal\user\EventSubscriber;

use Drupal\Core\Site\Settings;
use Drupal\user\Event\UserEvents;
use Drupal\user\Event\UserFloodEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Logs details of User Flood Control events.
 */
class UserFloodSubscriber implements EventSubscriberInterface {

  /**
   * The default logger service.
   *
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a UserFloodSubscriber.
   *
   * @param LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(?LoggerInterface $logger = NULL) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[UserEvents::FLOOD_BLOCKED_USER][] = ['blockedUser'];
    $events[UserEvents::FLOOD_BLOCKED_IP][] = ['blockedIp'];
    return $events;
  }

  /**
   * An attempt to login has been blocked based on user name.
   *
   * @param UserFloodEvent $floodEvent
   *   The flood event.
   */
  public function blockedUser(UserFloodEvent $floodEvent) {
    if (Settings::get('log_user_flood', TRUE)) {
      $uid = $floodEvent->getUid();
      if ($floodEvent->hasIp()) {
        $ip = $floodEvent->getIp();
        $this->logger->notice('Flood control blocked login attempt for uid %uid from %ip', ['%uid' => $uid, '%ip' => $ip]);
        return;
      }
      $this->logger->notice('Flood control blocked login attempt for uid %uid', ['%uid' => $uid]);
    }
  }

  /**
   * An attempt to login has been blocked based on IP.
   *
   * @param UserFloodEvent $floodEvent
   *   The flood event.
   */
  public function blockedIp(UserFloodEvent $floodEvent) {
    if (Settings::get('log_user_flood', TRUE)) {
      $this->logger->notice('Flood control blocked login attempt from %ip', ['%ip' => $floodEvent->getIp()]);
    }
  }

}
