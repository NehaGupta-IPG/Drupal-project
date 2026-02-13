<?php

declare(strict_types=1);

namespace Drupal\system\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives the revision_uuid_update plugin by entities supporting revision UUID.
 */
class RevisionUuidUpdateDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): self {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $entityType) {
      if ($entityType instanceof ContentEntityTypeInterface && $entityType->hasRevisionMetadataKey('revision_uuid')) {
        $this->derivatives[$entityTypeId] = $base_plugin_definition;
        $this->derivatives[$entityTypeId]['title'] = $this->t('Update %type revision UUIDs', [
          '%type' => $entityType->getLabel(),
        ]);
      }
    }
    return $this->derivatives;
  }

}
