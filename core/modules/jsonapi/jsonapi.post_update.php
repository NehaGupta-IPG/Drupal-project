<?php

/**
 * @file
 * Post update functions for JSON:API.
 */

/**
 * Installs JSON:API Write module if writes were previously allowed.
 */
function jsonapi_post_update_install_jsonapi_write(): void {
  $config = \Drupal::configFactory()->getEditable('jsonapi.settings');
  if ($config->get('read_only') === FALSE) {
    \Drupal::service('module_installer')->install(['jsonapi_write']);
  }
  $config->clear('read_only')->save();
}
