<?php

namespace Drupal\content_moderation\Plugin\Action;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Plugin\Action\PublishAction;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Alternate action plugin that can opt-out of modifying moderated entities.
 *
 * @see \Drupal\Core\Action\Plugin\Action\PublishAction
 */
class ModerationOptOutPublish extends PublishAction implements ContainerFactoryPluginInterface {

  /**
   * Moderation information service.
   *
   * @var ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * Bundle info service.
   *
   * @var EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * Messenger service.
   *
   * @var MessengerInterface
   */
  protected $messenger;

  /**
   * ModerationOptOutPublish constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param ModerationInformationInterface $moderation_info
   *   The moderation information service.
   * @param EntityTypeBundleInfoInterface $bundle_info
   *   Bundle info service.
   * @param MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ModerationInformationInterface $moderation_info, EntityTypeBundleInfoInterface $bundle_info, MessengerInterface $messenger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
    $this->moderationInfo = $moderation_info;
    $this->bundleInfo = $bundle_info;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function access($entity, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    if ($entity && $this->moderationInfo->isModeratedEntity($entity)) {
      $bundle_info = $this->bundleInfo->getBundleInfo($entity->getEntityTypeId());
      $bundle_label = $bundle_info[$entity->bundle()]['label'];
      $this->messenger->addWarning($this->t("@bundle @label were skipped as they are under moderation and may not be directly published.", [
        '@bundle' => $bundle_label,
        '@label' => $entity->getEntityType()->getPluralLabel(),
      ]));
      $result = AccessResult::forbidden('Cannot directly publish moderated entities.');
      return $return_as_object ? $result : $result->isAllowed();
    }
    return parent::access($entity, $account, $return_as_object);
  }

}
