<?php

/**
 * @file
 * Post update functions for Single-Directory Components.
 */

/**
 * Uninstall Single-Directory Components if installed.
 */
function sdc_post_update_sdc_uninstall(): void {
  if (\Drupal::moduleHandler()->moduleExists('sdc')) {
    \Drupal::service('module_installer')->uninstall(['sdc'], FALSE);
  }
}
