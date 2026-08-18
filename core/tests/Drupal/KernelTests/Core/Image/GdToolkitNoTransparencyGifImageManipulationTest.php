<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Image;

use Drupal\system\Plugin\ImageToolkit\GDToolkit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * GD image toolkit image manipulation of no-transparency GIF images.
 */
#[CoversClass(GDToolkit::class)]
#[Group('Image')]
#[RequiresPhpExtension('gd')]
#[RunTestsInSeparateProcesses]
class GdToolkitNoTransparencyGifImageManipulationTest extends GdToolkitImageManipulationTestBase {

  /**
   * {@inheritdoc}
   */
  protected string $sourceTestImage = 'image-test-no-transparency.gif';

  /**
   * {@inheritdoc}
   */
  public static function providerOperationTestCases(): array {
    $ret = parent::providerOperationTestCases();

    // The test gif that does not have transparency color set is a special
    // case.
    foreach ($ret as $test_case => &$data) {
      foreach ($data[3]['corners'] as &$expected_color) {
        if ($test_case == 'desaturate') {
          // For desaturating, keep the expected color from the test data, but
          // set alpha channel to fully opaque.
          $expected_color[3] = 0;
        }
        elseif ($expected_color === static::TRANSPARENT) {
          // Set expected pixel to yellow where the others have
          // transparent.
          $expected_color = static::YELLOW;
        }
      }
    }

    if (PHP_VERSION_ID >= 80600 && array_key_exists('rotate_transparent_5', $ret)) {
      // On PHP 8.6+ the enlarged rotation canvas puts the corner sample on
      // the transparent background, black in the GIF palette.
      $ret['rotate_transparent_5'][3]['corners'][0] = [0, 0, 0, 127];
    }

    return $ret;
  }

}
