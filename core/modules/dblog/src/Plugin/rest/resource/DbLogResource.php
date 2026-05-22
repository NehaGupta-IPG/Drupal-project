<?php

namespace Drupal\dblog\Plugin\rest\resource;

use Drupal\Core\Database\Database;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a resource for database watchdog log entries.
 */
#[RestResource(
  id: "dblog",
  label: new TranslatableMarkup("Watchdog database log"),
  uri_paths: [
    "canonical" => "/dblog/{id}",
  ]
)]
class DbLogResource extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * Returns a watchdog log entry for the specified ID.
   *
   * @param int $id
   *   The ID of the watchdog log entry.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the log entry.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the log entry was not found.
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown when no log entry was provided.
   */
  public function get($id = NULL) {
    if ($id) {
      $record = Database::getConnection()->select('watchdog', 'w')
        ->fields('w')
        ->condition('wid', (int) $id)
        ->execute()
        ->fetchAssoc();
      if (!empty($record)) {
        if (isset($record['timestamp']) && ($record['timestamp'] instanceof UTCDateTime)) {
          $record['timestamp'] = (int) $record['timestamp']->__toString();
          $record['timestamp'] = $record['timestamp'] / 1000;
          $record['timestamp'] = (string) $record['timestamp'];
        }
        if (isset($record['variables']) && ($record['variables'] instanceof Binary)) {
          $record['variables'] = $record['variables']->getData();
        }

        return new ResourceResponse($record);
      }

      throw new NotFoundHttpException("Log entry with ID '$id' was not found");
    }

    throw new BadRequestHttpException('No log entry ID was provided');
  }

}
