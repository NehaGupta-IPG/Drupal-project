<?php

namespace Drupal\migrate\Event;

/**
 * Interface for plugins that react to pre- or post-import events.
 */
interface ImportAwareInterface {

  /**
   * Performs pre-import tasks.
   *
   * @param MigrateImportEvent $event
   *   The pre-import event object.
   */
  public function preImport(MigrateImportEvent $event);

  /**
   * Performs post-import tasks.
   *
   * @param MigrateImportEvent $event
   *   The post-import event object.
   */
  public function postImport(MigrateImportEvent $event);

}
