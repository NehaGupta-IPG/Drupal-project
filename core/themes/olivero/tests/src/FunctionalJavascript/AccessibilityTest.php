<?php

declare(strict_types=1);

namespace Drupal\Tests\olivero\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\AccessibilityTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Run a basic axe-core test on some pages.
 */
#[Group('olivero')]
#[Group('accessibility')]
#[RunTestsInSeparateProcesses]
class AccessibilityTest extends AccessibilityTestBase {

  /**
   * {@inheritdoc}
   */
  public static function provider(): \Generator {

    yield 'Homepage' => [
      '<front>',
      FALSE,
          // @todo remove the disabled 'region' rule in https://drupal.org/i/3318396.
          [
            'rules' => [
              'region' => ['enabled' => FALSE],
            ],
          ],
    ];

    yield 'Login' => [
      '/user/login',
      FALSE,
          // @todo remove the disabled 'region' rule in https://drupal.org/i/3318396.
          [
            'rules' => [
              'region' => ['enabled' => FALSE],
            ],
          ],
    ];

    yield 'Search' => [
      '/search/node',
      FALSE,
          // @todo remove the heading and duplicate id rules below in
          //   https://drupal.org/i/3318398.
          [
            'rules' => [
              'heading-order' => ['enabled' => FALSE],
              'duplicate-id-aria' => ['enabled' => FALSE],
            ],
          ],
    ];
  }

}
