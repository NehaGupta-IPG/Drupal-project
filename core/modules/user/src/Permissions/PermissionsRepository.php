<?php

declare(strict_types=1);

namespace Drupal\user\Permissions;

/**
 * Implementation of the PermissionsRepositoryInterface interface.
 *
 * @see \Drupal\user\Permissions\PermissionsRepositoryInterface
 */
class PermissionsRepository implements PermissionsRepositoryInterface {
  /**
   * Contains the list of permissions.
   *
   * @var array
   */
  private $data;

  /**
   * The constructor.
   */
  public function __construct() {
    $this->data = [];
  }

  /**
   * {@inheritdoc}
   */
  public function addPermission(string $provider, array $permission): void {
    if (empty($permission['title']) || empty($permission['key'])) {
      throw new \InvalidArgumentException('Invalid permission format: ' . json_encode($permission));
    }

    $permission += [
      'description' => NULL,
      'provider' => $provider,
      'weight' => 0,
      'extended_help' => '',
    ];

    $this->data[$provider]['permissions']['' . $permission['key']] = $permission;
  }

  /**
   * {@inheritdoc}
   */
  public function addSection(string $provider, array $section): void {
    if (empty($section['title'])) {
      throw new \InvalidArgumentException('Invalid section format: ' . json_encode($section));
    }

    $section += [
      'description' => NULL,
      'provider' => $provider,
      'weight' => 0,
      'extended_help' => '',
    ];

    $this->data[$provider]['sections']['' . $section['title']] = $section;
  }

  /**
   * {@inheritdoc}
   */
  public function setMain(string $provider, array $main): void {
    if (empty($main['extended_help'])) {
      throw new \InvalidArgumentException('Invalid main format: ' . json_encode($main));
    }

    $this->data[$provider]['main']['extended_help'] = $main['extended_help'];
  }

  /**
   * {@inheritdoc}
   */
  public function sortAll(): void {
    foreach ($this->data as $provider => $items) {
      if (empty($items['permissions'])) {
        continue;
      }

      $sections = !empty($items['sections']) ? $items['sections'] : [];

      foreach ($items['permissions'] as $key => $permission) {
        $sectionWeight = 0;
        if (!empty($permission['section'])) {
          $sectionKey = $permission['section'];
          if (!empty($sections[$sectionKey]['weight'])) {
            $sectionWeight = 1000 * $sections[$sectionKey]['weight'];
          }
        }
        $this->data[$provider]['permissions'][$key]['overall_weight'] = $sectionWeight + ($permission['weight'] ?? 0);
      }

      if (!empty($this->data[$provider]['permissions'])) {
        uasort($this->data[$provider]['permissions'], function ($a, $b) {
          return $a['overall_weight'] <=> $b['overall_weight'];
        });
      }

      if (!empty($this->data[$provider]['sections'])) {
        uasort($this->data[$provider]['sections'], function ($a, $b) {
          return $a['weight'] <=> $b['weight'];
        });
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getProviders(): array {
    return array_keys($this->data);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllPermissions(PermissionsRepositoryReturnStyle $style = PermissionsRepositoryReturnStyle::SectionArray): array {
    $ret = [];

    foreach ($this->data as $provider => $items) {
      if (!empty($items['permissions'])) {
        $temp = $items['permissions'];

        if ($style === PermissionsRepositoryReturnStyle::SectionArray) {
          $temp = $this->addSectionsToPermissions($provider, $temp);
        }
        $ret = array_merge($ret, $temp);
      }
    }

    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function getProviderData(string $provider, PermissionsRepositoryReturnStyle $style = PermissionsRepositoryReturnStyle::SectionArray): array {
    if (empty($this->data[$provider])) {
      return [];
    }

    $ret = $this->data[$provider];

    if ($style === PermissionsRepositoryReturnStyle::SectionArray) {
      $ret['permissions'] = $this->addSectionsToPermissions($provider, $ret['permissions']);
    }

    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllData(PermissionsRepositoryReturnStyle $style = PermissionsRepositoryReturnStyle::SectionArray): array {
    if ($style === PermissionsRepositoryReturnStyle::SectionKey) {
      return $this->data;
    }

    $ret = $this->data;

    foreach ($ret as $provider => $items) {
      $ret[$provider]['permissions'] = $this->addSectionsToPermissions($provider, $items['permissions']);
    }

    return $ret;
  }

  /**
   * Utility method.
   *
   * Copies the sections data from the top level into each
   * set of permissions.
   *
   * @param string $provider
   *   The provider machine name, such as a module machine name.
   * @param array $permissions
   *   The permissions data.
   *
   * @return array
   *   The permissions, possibly empty.
   */
  protected function addSectionsToPermissions($provider, $permissions) {
    foreach ($permissions as $name => $permission) {
      if (!empty($permission['section'])) {
        $sectionKey = $permission['section'];
        if (!empty($this->data[$provider]['sections'][$sectionKey])) {
          $permissions[$name]['section'] = $this->data[$provider]['sections'][$sectionKey];
        }
      }
    }
    return $permissions;
  }

}
