<?php

declare(strict_types=1);

namespace Drupal\Tests\stark\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\AccessibilityTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Run a basic axe-core test on some pages.
 */
#[Group('stark')]
#[Group('accessibility')]
#[RunTestsInSeparateProcesses]
class AccessibilityTest extends AccessibilityTestBase {

  /**
   * {@inheritdoc}
   */
  public static function provider(): \Generator {

    // @todo move these to default admin because it will be the default theme?
    yield 'Homepage' => [
      'path' => '<front>',
      // @todo remove the disabled 'region' rule in https://drupal.org/i/3318396.
      'axe_options' => [
        'rules' => [
          'region' => ['enabled' => FALSE],
        ],
      ],
    ];

    yield 'Login' => [
      'path' => '/user/login',
      // @todo remove the disabled 'region' rule in https://drupal.org/i/3318396.
      'axe_options' => [
        'rules' => [
          'region' => ['enabled' => FALSE],
        ],
      ],
    ];
  }

}
