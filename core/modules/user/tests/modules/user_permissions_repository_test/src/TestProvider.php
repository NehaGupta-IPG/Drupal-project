<?php

declare(strict_types=1);

namespace Drupal\user_permissions_repository_test;

use Drupal\user\Permissions\ProvidesPermissionsInterface;
use Drupal\user\Permissions\ProvidesSectionsInterface;

// cspell:ignore testprovider

/**
 * Returns responses to test the permissions repository.
 */
class TestProvider implements ProvidesPermissionsInterface, ProvidesSectionsInterface {

  /**
   * {@inheritdoc}
   */
  public function getPerms(): array {
    return [
      'perm_from_testprovider' => [
        'title' => 'Permission from TestProvider',
        'section' => 'section_from_testprovider',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSections(): array {
    return [
      'section_from_testprovider' => [
        'title' => 'Section from TestProvider',
        'extended_help' => 'This is just for testing',
        'weight' => 0,
      ],
      'unused_section' => [
        'title' => 'unused_section',
      ],
    ];
  }

}
