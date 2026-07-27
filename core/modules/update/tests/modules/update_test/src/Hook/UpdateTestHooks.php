<?php

declare(strict_types=1);

namespace Drupal\update_test\Hook;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for update_test.
 */
class UpdateTestHooks {

  /**
   * Implements hook_system_info_alter().
   *
   * Checks the 'update_test.settings:system_info' configuration and sees if we
   * need to alter the system info for the given $file based on the setting. The
   * setting is expected to be a nested associative array. If the key '#all' is
   * defined, its subarray will include .info.yml keys and values for all
   * modules and themes on the system. Otherwise, the settings array is keyed by
   * the module or theme short name ($file->name) and the subarrays contain
   * settings just for that module or theme.
   */
  #[Hook('system_info_alter')]
  public function systemInfoAlter(&$info, Extension $file): void {
    $setting = \Drupal::config('update_test.settings')->get('system_info');
    foreach (['#all', $file->getName()] as $id) {
      if (!empty($setting[$id])) {
        foreach ($setting[$id] as $key => $value) {
          $info[$key] = $value;
        }
      }
    }
  }

  /**
   * Implements hook_update_projects_alter().
   *
   * Checks the 'update_test.settings:allowed_projects' configuration and, if it
   * is a non-empty list, removes any project not in that list from the update
   * status report. This keeps tests isolated from contributed modules and
   * themes that happen to be present in the code base but are irrelevant to the
   * test: such extra projects would otherwise add tables to the report (shifting
   * the CSS locators the tests rely on) and, because they have no release
   * history fixture, exhaust the shared per-URL fetch-failure limit and prevent
   * the projects under test from being fetched.
   */
  #[Hook('update_projects_alter')]
  public function updateProjectsAlter(&$projects): void {
    $allowed = \Drupal::config('update_test.settings')->get('allowed_projects');
    if (!empty($allowed)) {
      $projects = array_intersect_key($projects, array_flip($allowed));
    }
  }

  /**
   * Implements hook_update_status_alter().
   *
   * Checks the 'update_test.settings:update_status' configuration and sees if
   * we need to alter the update status for the given project based on the
   * setting. The setting is expected to be a nested associative array. If the
   * key '#all' is defined, its subarray will include .info.yml keys and values
   * for all modules and themes on the system. Otherwise, the settings array is
   * keyed by the module or theme short name and the subarrays contain settings
   * just for that module or theme.
   */
  #[Hook('update_status_alter')]
  public function updateStatusAlter(&$projects): void {
    $setting = \Drupal::config('update_test.settings')->get('update_status');
    if (!empty($setting)) {
      foreach ($projects as $project_name => &$project) {
        foreach (['#all', $project_name] as $id) {
          if (!empty($setting[$id])) {
            foreach ($setting[$id] as $key => $value) {
              $project[$key] = $value;
            }
          }
        }
      }
    }
  }

}
