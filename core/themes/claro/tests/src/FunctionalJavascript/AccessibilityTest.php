<?php

declare(strict_types=1);

namespace Drupal\Tests\claro\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\AccessibilityTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Run a basic axe-core test on some pages.
 */
#[Group('claro')]
#[Group('accessibility')]
#[RunTestsInSeparateProcesses]
class AccessibilityTest extends AccessibilityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static array $testCases = [
    'User Edit' => [
      '/user/1/edit',
      TRUE,
    ],
    'Create Article' => [
      '/node/add/article',
      TRUE,
    ],
    'Create Page' => [
      '/node/add/page',
      TRUE,
    ],
    'Content Page' => [
      '/admin/content',
      TRUE,
    ],
    'Structure Page' => [
      '/admin/structure',
      TRUE,
    ],
    'Add content type' => [
      '/admin/structure/types/add',
      TRUE,
    ],
    'Add vocabulary' => [
      '/admin/structure/taxonomy/add',
      TRUE,
    ],
      // @todo remove the skipped rules below in https://drupal.org/i/3318394.
    'Structure | Block' => [
      '/admin/structure/block',
      TRUE,
        [
          'rules' => [
            'color-contrast' => ['enabled' => FALSE],
            'duplicate-id-active' => ['enabled' => FALSE],
            'region' => ['enabled' => FALSE],
          ],
        ],
    ],
      // Tests long breadcrumb for https://drupal.org/i/3223147.
    'Manage text format, mobile' => [
      '/admin/config/content/formats/manage/restricted_html',
      TRUE,
        [
          // This is the only way to add WCAG 2.2 tags to defaults.
          // See https://github.com/dequelabs/axe-core/issues/4717.
          'runOnly' => [
            'type' => 'tag',
            'values' => [
              'wcag2a',
              'wcag2aa',
              'wcag21a',
              'wcag21aa',
              'best-practice',
              'wcag22a',
              'wcag22aa',
            ],
          ],
        ],
      'browser_options' => [
        'resize_window' => [
            // Minimum window size seems to be 500px.
          'width' => 500,
            // Height used by Lighthouse for mobile.
          'height' => 823,
        ],
      ],
    ],
  ];

}
