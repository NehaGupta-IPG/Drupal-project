<?php

// phpcs:disable

namespace Drupal;

/**
 * PreBootstrapDefinition.
 */
class PreBootstrapDefinition {

  public const array DEFINITION = array (
  'parameters' => 
  array (
    'drupal_core_path' => 'core',
    'web_root' => '',
    'vendor_dir' => 'vendor',
  ),
  'services' => 
  array (
    'ApplicationRootGuesser' => 
    array (
      'class' => 'Drupal\\Core\\Boot\\ApplicationRootGuesser',
    ),
    'InitializeEnvironment' => 
    array (
      'class' => 'Drupal\\Core\\Boot\\InitializeEnvironment',
      'arguments' => 
      array (
        0 => '@ApplicationRootGuesser',
      ),
    ),
  ),
);

}
