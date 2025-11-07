<?php

declare(strict_types=1);

namespace Drupal\file\EventSubscriber;

use Drupal\Core\DefaultContent\PreEntityImportEvent;
use Drupal\Core\DefaultContent\PreExportEvent;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileUriItem;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to default content-related events.
 *
 * @internal
 *   Event subscribers are internal.
 */
class DefaultContentSubscriber implements EventSubscriberInterface {

  use LoggerAwareTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreExportEvent::class => 'preExport',
      PreEntityImportEvent::class => 'preEntityImport',
    ];
  }

  /**
   * Reacts before an entity is exported.
   *
   * @param \Drupal\Core\DefaultContent\PreExportEvent $event
   *   The event object.
   */
  public function preExport(PreExportEvent $event): void {
    $entity = $event->entity;

    if ($entity instanceof FileInterface) {
      $uri = $entity->getFileUri();
      // Ensure the file has a name (`getFilename()` may return NULL).
      $name = $entity->getFilename() ?? basename($uri);
      $entity->setFilename($name);

      if (file_exists($uri)) {
        $event->metadata->addAttachment($uri, $name);

        // URL-encode the file URI so that it can pass strict validation on
        // import. This is reversed in ::preEntityImport().
        $event->setCallback(
          'field_item:file_uri',
          fn (FileUriItem $item): array => array_map('urlencode', $item->getValue()),
        );
      }
      else {
        $this->logger?->warning('The file (%uri) associated with file entity %name does not exist.', [
          '%uri' => $uri,
          '%name' => $entity->label(),
        ]);
      }
    }
  }

  /**
   * Reacts before a file entity is imported.
   *
   * @param \Drupal\Core\DefaultContent\PreExportEvent $event
   *   The event object.
   */
  public function preEntityImport(PreEntityImportEvent $event): void {
    if ($event->metadata['entity_type'] !== 'file') {
      return;
    }
    $event->data['default']['uri'] = array_map(
      'urldecode',
      $event->data['default']['uri'],
    );
  }

}
