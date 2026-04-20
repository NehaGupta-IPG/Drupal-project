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
   * Setup callback for 'Manage text format, mobile' test case.
   */
  protected function setupManageTextFormatMobile(): void {
    $session = $this->getSession();

    $session->resizeWindow(
      500,
      823
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function provider(): \Generator {

    yield 'User Edit' => [
      '/user/1/edit',
      TRUE,
    ];

    yield 'Create Article' => [
      '/node/add/article',
      TRUE,
    ];

    yield 'Create Page' => [
      '/node/add/page',
      TRUE,
    ];

    yield 'Content Page' => [
      '/admin/content',
      TRUE,
    ];

    yield 'Structure Page' => [
      '/admin/structure',
      TRUE,
    ];

    yield 'Add content type' => [
      '/admin/structure/types/add',
      TRUE,
    ];

    yield 'Add vocabulary' => [
      '/admin/structure/taxonomy/add',
      TRUE,
    ];

    // @todo remove the skipped rules below in https://drupal.org/i/3318394.
    yield 'Structure | Block' => [
      '/admin/structure/block',
      TRUE,
        [
          'rules' => [
            'color-contrast' => ['enabled' => FALSE],
            'duplicate-id-active' => ['enabled' => FALSE],
            'region' => ['enabled' => FALSE],
          ],
        ],
    ];

    // Tests long breadcrumb for https://drupal.org/i/3223147.
    yield 'Manage text format, mobile' => [
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
      'setupManageTextFormatMobile',
    ];
  }

}
