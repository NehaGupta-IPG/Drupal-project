<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\AccessibilityTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Run a basic axe-core test on some pages.
 */
#[Group('default_admin')]
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
      'path' => '/user/1/edit',
      'admin' => TRUE,
      'axe_options' => [
        'rules' => [
          // @todo remove after https://drupal.org/i/3318396.
          'region' => ['enabled' => FALSE],
        ],
      ],
    ];

    yield 'Create Article' => [
      'path' => '/node/add/article',
      'admin' => TRUE,
      'axe_options' => [
        'rules' => [
          // @todo remove after https://drupal.org/i/3623388.
          'color-contrast' => ['enabled' => FALSE],
          // @todo remove after https://drupal.org/i/3318396.
          'region' => ['enabled' => FALSE],
        ],
      ],
    ];

    yield 'Create Page' => [
      'path' => '/node/add/page',
      'admin' => TRUE,
      'axe_options' => [
        'rules' => [
          // @todo remove after https://drupal.org/i/3623388.
          'color-contrast' => ['enabled' => FALSE],
          // @todo remove after https://drupal.org/i/3318396.
          'region' => ['enabled' => FALSE],
        ],
      ],
    ];

    yield 'Content Page' => [
      'path' => '/admin/content',
      'admin' => TRUE,
      'axe_options' => [
        'rules' => [
          // @todo remove after https://drupal.org/i/3318396.
          'region' => ['enabled' => FALSE],
        ],
      ],
    ];

    yield 'Structure Page' => [
      'path' => '/admin/structure',
      'admin' => TRUE,
      'axe_options' => [
        'rules' => [
          // @todo remove after https://drupal.org/i/3318396.
          'region' => ['enabled' => FALSE],
        ],
      ],
    ];

    yield 'Add content type' => [
      'path' => '/admin/structure/types/add',
      'admin' => TRUE,
      'axe_options' => [
        'rules' => [
          // @todo remove after https://drupal.org/i/3318396.
          'region' => ['enabled' => FALSE],
        ],
      ],
    ];

    yield 'Add vocabulary' => [
      'path' => '/admin/structure/taxonomy/add',
      'admin' => TRUE,
      'axe_options' => [
        'rules' => [
          // @todo remove after https://drupal.org/i/3318396.
          'region' => ['enabled' => FALSE],
        ],
      ],
    ];

    // @todo remove the skipped rules below in https://drupal.org/i/3318394.
    yield 'Structure | Block' => [
      'path' => '/admin/structure/block',
      'admin' => TRUE,
      'axe_options' => [
        'rules' => [
          'color-contrast' => ['enabled' => FALSE],
          'duplicate-id-active' => ['enabled' => FALSE],
          'region' => ['enabled' => FALSE],
        ],
      ],
    ];

    // Tests long breadcrumb for https://drupal.org/i/3223147.
    yield 'Manage text format, mobile' => [
      'path' => '/admin/config/content/formats/manage/restricted_html',
      'admin' => TRUE,
      'axe_options' => [
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
      'setup_callback' => 'setupManageTextFormatMobile',
    ];
  }

}
