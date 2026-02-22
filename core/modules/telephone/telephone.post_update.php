<?php

/**
 * @file
 * Post update functions for Telephone.
 */

use Drupal\Core\Batch\BatchBuilder;

/**
 * Ensures that all telephone fields has size attribute in config.
 */
function telephone_post_update_add_size_attribute() {
  $configNames = \Drupal::service('config.storage')->listAll('core.entity_form_display');
  $batch_builder = new BatchBuilder();
  $batch_builder
    ->setTitle(t('Updating Config ..'))
    ->setFinishCallback([
      '\Drupal\telephone\TelephoneConfig',
      'exportFinished',
    ])
    ->setInitMessage(t('Updating Config.'))
    ->setErrorMessage(t('The process has encountered an error.'));

  foreach ($configNames as $configName) {
    $batch_builder->addOperation([
      '\Drupal\telephone\TelephoneConfig',
      'processTelephoneConfig',
    ],
      [
        $configName,
      ]);
  }

  batch_set($batch_builder->toArray());

}
