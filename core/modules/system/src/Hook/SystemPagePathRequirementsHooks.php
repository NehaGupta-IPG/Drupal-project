<?php

namespace Drupal\system\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Runtime requirements related to configured system page paths.
 */
class SystemPagePathRequirementsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $invalid_pages = $this->getInvalidConfiguredSystemPages();
    if (empty($invalid_pages)) {
      return [];
    }

    return [
      'system_page_paths' => [
        'title' => $this->t('Configured system pages'),
        'value' => $this->t('Some configured pages cannot be displayed'),
        'severity' => RequirementSeverity::Warning,
        'description' => [
          [
            '#markup' => $this->t('These page paths in your site settings do not point to an existing page:'),
          ],
          [
            '#theme' => 'item_list',
            '#items' => $invalid_pages,
          ],
          [
            '#markup' => $this->t('Update these paths on the <a href=":url">Site information</a> page.', [
              ':url' => Url::fromRoute('system.site_information_settings')->toString(),
            ]),
          ],
        ],
      ],
    ];
  }

  /**
   * Gets invalid configured system pages from system.site.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   Invalid configured system pages.
   */
  private function getInvalidConfiguredSystemPages(): array {
    /** @var \Drupal\Core\Path\PathValidatorInterface $path_validator */
    $path_validator = \Drupal::service('path.validator');
    assert($path_validator instanceof PathValidatorInterface);

    $configured_pages = \Drupal::config('system.site')->get('page') ?? [];
    $page_definitions = [
      'front' => [
        'label' => $this->t('Front page'),
        'empty_allowed' => FALSE,
      ],
      '403' => [
        'label' => $this->t('403 page'),
        'empty_allowed' => TRUE,
      ],
      '404' => [
        'label' => $this->t('404 page'),
        'empty_allowed' => TRUE,
      ],
    ];

    $invalid_pages = [];
    foreach ($page_definitions as $config_key => $definition) {
      $path = $configured_pages[$config_key] ?? '';
      if (!is_string($path)) {
        $path = '';
      }

      if ($path === '' && $definition['empty_allowed']) {
        continue;
      }
      if ($path !== '' && $path_validator->getUrlIfValidWithoutAccessCheck($path)) {
        continue;
      }

      $path_display_value = $path === '' ? $this->t('[empty]') : $path;
      $invalid_pages[] = $this->t('@label (<code>system.site:page.@key</code>): @path', [
        '@label' => $definition['label'],
        '@key' => $config_key,
        '@path' => $path_display_value,
      ]);
    }

    return $invalid_pages;
  }

}

