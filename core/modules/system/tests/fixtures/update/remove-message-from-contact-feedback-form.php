<?php

/**
 * @file
 * Empties the message of the contact feedback form.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$data = $connection->select('config')
  ->condition('name', 'contact.form.feedback')
  ->fields('config', ['data'])
  ->execute()
  ->fetchField();
$data = unserialize($data);
// Empty message to confirm that preSave change the value to NULL.
// @see \Drupal\contact\Entity\ContactForm::preSave()
// @see \Drupal\Tests\contact\Functional\Update\ContactFormUpdatePathTest::testRunUpdates()
$data['message'] = "";
$connection->update('config')
  ->condition('name', 'contact.form.feedback')
  ->fields([
    'data' => serialize($data),
  ])
  ->execute();
