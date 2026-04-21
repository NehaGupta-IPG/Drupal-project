<?php

namespace Drupal\contact;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityNullStorage;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Storage handler for contact message entities.
 *
 * Extends ContentEntityNullStorage to add email sending and flood protection
 * when a contact message is saved, enabling these features for REST and
 * JSON:API in addition to the form UI.
 */
class ContactMessageStorage extends ContentEntityNullStorage implements EntityHandlerInterface {

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityFieldManagerInterface $entity_field_manager,
    CacheBackendInterface $cache,
    MemoryCacheInterface $memory_cache,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    protected readonly MailHandlerInterface $mailHandler,
    protected readonly FloodInterface $flood,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly AccountInterface $currentUser,
  ) {
    parent::__construct($entity_type, $entity_field_manager, $cache, $memory_cache, $entity_type_bundle_info);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity_field.manager'),
      $container->get('cache.entity'),
      $container->get('entity.memory_cache'),
      $container->get('entity_type.bundle.info'),
      $container->get('contact.mail_handler'),
      $container->get('flood'),
      $container->get('config.factory'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function save(EntityInterface $entity) {
    if (!$entity->isNew()) {
      return;
    }

    $this->checkFlood();

    $this->mailHandler->sendMailMessages($entity, $this->currentUser);

    $this->flood->register(
      'contact',
      $this->configFactory->get('contact.settings')->get('flood.interval'),
    );
  }

  /**
   * Checks flood control limits.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException
   *   When the flood limit has been exceeded.
   */
  protected function checkFlood(): void {
    if ($this->currentUser->hasPermission('administer contact forms')) {
      return;
    }

    $limit = $this->configFactory->get('contact.settings')->get('flood.limit');
    $interval = $this->configFactory->get('contact.settings')->get('flood.interval');

    if (!$this->flood->isAllowed('contact', $limit, $interval)) {
      throw new TooManyRequestsHttpException(
        NULL,
        'You cannot send more than @limit messages in @interval. Try again later.',
        [
          '@limit' => $limit,
          '@interval' => (string) $interval,
        ],
      );
    }
  }

}
