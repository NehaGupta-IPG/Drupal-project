<?php

namespace Drupal\media\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\media\MediaMetadataRefresher;
use Drupal\media\MediaSourceStatus;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Refreshes metadata (including the thumbnail) of media items from source.
 *
 * Media items whose source is missing or unavailable are skipped and listed
 * in a warning, so a bulk refresh never removes data without confirmation.
 */
#[Action(
  id: 'media_update_metadata',
  label: new TranslatableMarkup('Refresh from source'),
  type: 'media',
)]
class UpdateMetadataAction extends ActionBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly MediaMetadataRefresher $refresher,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(MediaMetadataRefresher::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities): void {
    $skipped = [];
    foreach ($entities as $entity) {
      if (!$this->refreshIfAvailable($entity)) {
        $skipped[] = $entity->label();
      }
    }
    if ($skipped) {
      $this->messenger()->addWarning($this->formatPlural(
        count($skipped),
        'Skipped media item %labels: its source is missing or unavailable.',
        'Skipped @count media items whose source is missing or unavailable: %labels.',
        ['%labels' => implode(', ', $skipped)],
      ));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL): void {
    if ($entity) {
      $this->refreshIfAvailable($entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\Core\Entity\EntityInterface $object */
    $result = $object->access('update', $account, TRUE);
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * Refreshes a media item unless its source is missing or unavailable.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media item.
   *
   * @return bool
   *   TRUE if the media item was refreshed, FALSE if it was skipped.
   */
  protected function refreshIfAvailable($media): bool {
    if ($this->refresher->check($media) !== MediaSourceStatus::Available) {
      return FALSE;
    }
    $this->refresher->refresh($media);
    return TRUE;
  }

}
