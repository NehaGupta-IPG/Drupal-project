<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Kernel\process;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\Route;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// cspell:ignore nzdt
/**
 * Tests the route process plugin.
 */
#[CoversClass(Route::class)]
#[Group('migrate')]
#[RunTestsInSeparateProcesses]
class RouteTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // We have to configure a front page since
    // PathProcessorFront::processInbound relies on it for missing paths.
    $this->config('system.site')
      ->set('page.front', 'user')
      ->save();
  }

  /**
   * Tests Route plugin based on providerTestRoute() values.
   *
   * @param mixed $value
   *   Input value for the Route process plugin.
   * @param array $expected
   *   The expected results from the Route transform process.
   */
  #[DataProvider('providerTestRoute')]
  public function testRoute($value, $expected): void {
    $actual = $this->doTransform($value);
    $this->assertSame($expected, $actual);
  }

  /**
   * Data provider for testRoute().
   *
   * @return array
   *   An array of arrays, where the first element is the input to the Route
   *   process plugin, and the second is the expected results.
   */
  public static function providerTestRoute() {
    return [
      'Valid internal link path and options' => [
        'data' => [
          'user/login',
          [
            'attributes' => [
              'title' => 'Test menu link 1',
            ],
          ],
        ],
        'expected' => [
          'route_name' => 'user.login',
          'route_parameters' => [],
          'options' => [
            'query' => [],
            'attributes' => [
              'title' => 'Test menu link 1',
            ],
          ],
          'url' => NULL,
        ],
      ],
      'Valid internal link path and empty options' => [
        'data' => [
          'user/login',
          [],
        ],
        'expected' => [
          'route_name' => 'user.login',
          'route_parameters' => [],
          'options' => [
            'query' => [],
          ],
          'url' => NULL,
        ],
      ],
      'Valid internal link path and no options' => [
        'data' => 'user/login',
        'expected' => [
          'route_name' => 'user.login',
          'route_parameters' => [],
          'options' => [
            'query' => [],
          ],
          'url' => NULL,
        ],
      ],
      'Valid internal link path and non-array options' => [
        'data' => [
          'user/login',
          'options',
        ],
        'expected' => [
          'route_name' => 'user.login',
          'route_parameters' => [],
          'options' => [
            'query' => [],
          ],
          'url' => NULL,
        ],
      ],
      'Invalid internal link path' => [
        'data' => 'users',
        'expected' => [],
      ],
      'Valid internal link path with parameter' => [
        'data' => [
          'system/timezone/nzdt',
          [
            'attributes' => [
              'title' => 'Show NZDT',
            ],
          ],
        ],
        'expected' => [
          'route_name' => 'system.timezone',
          'route_parameters' => [
            'abbreviation' => 'nzdt',
            'offset' => -1,
            'is_daylight_saving_time' => NULL,
          ],
          'options' => [
            'query' => [],
            'attributes' => [
              'title' => 'Show NZDT',
            ],
          ],
          'url' => NULL,
        ],
      ],
      'Valid external link path and options' => [
        'data' => [
          'https://www.drupal.org',
          [
            'attributes' => [
              'title' => 'Drupal',
            ],
          ],
        ],
        'expected' => [
          'route_name' => NULL,
          'route_parameters' => [],
          'options' => [
            'attributes' => [
              'title' => 'Drupal',
            ],
          ],
          'url' => 'https://www.drupal.org',
        ],
      ],
      'Valid external link path with query string and options' => [
        'data' => [
          'https://www.drupal.org/user/1/edit?pass-reset-token=QgtDKcRV4e4fjg6v2HTa6CbWx-XzMZ5XBZTufinqsM73qIhscIuU_BjZ6J2tv4dQI6N50ZJOag',
          [
            'attributes' => [
              'title' => 'Drupal password reset',
            ],
          ],
        ],
        'expected' => [
          'route_name' => NULL,
          'route_parameters' => [],
          'options' => [
            'attributes' => [
              'title' => 'Drupal password reset',
            ],
          ],
          'url' => 'https://www.drupal.org/user/1/edit?pass-reset-token=QgtDKcRV4e4fjg6v2HTa6CbWx-XzMZ5XBZTufinqsM73qIhscIuU_BjZ6J2tv4dQI6N50ZJOag',
        ],
      ],
      'Null link path with options' => [
        'data' => [
          NULL,
          NULL,
        ],
        'expected' => [
          'route_name' => 'user.page',
          'route_parameters' => [],
          'options' => [
            'query' => [],
          ],
          'url' => NULL,
        ],
      ],
    ];
  }

  /**
   * Tests Route plugin based on providerTestRoute() values.
   *
   * @param mixed $value
   *   Input value for the Route process plugin.
   * @param array $expected
   *   The expected results from the Route transform process.
   */
  #[DataProvider('providerTestRouteWithParamQuery')]
  public function testRouteWithParamQuery($value, $expected): void {
    // Create a user so that user/1/edit is a valid path.
    $this->setUpCurrentUser();
    $this->installConfig(['user']);

    $actual = $this->doTransform($value);
    $this->assertSame($expected, $actual);
  }

  /**
   * Data provider for testRouteWithParamQuery().
   *
   * @return array
   *   An array of arrays, where the first element is the input to the Route
   *   process plugin, and the second is the expected results.
   */
  public static function providerTestRouteWithParamQuery() {
    $values = [];
    $expected = [];
    // Valid link path with query options and parameters.
    $values[0] = [
      'user/1/edit',
      [
        'attributes' => [
          'title' => 'Edit admin',
        ],
        'query' => [
          'destination' => '/admin/people',
        ],
      ],
    ];
    $expected[0] = [
      'route_name' => 'entity.user.edit_form',
      'route_parameters' => [
        'user' => '1',
      ],
      'options' => [
        'attributes' => [
          'title' => 'Edit admin',
        ],
        'query' => [
          'destination' => '/admin/people',
        ],
      ],
      'url' => NULL,
    ];

    return [
      // Test with valid link path with parameters and options.
      [$values[0], $expected[0]],
    ];
  }

  /**
   * Transforms link path data to a route.
   *
   * @param array|string $value
   *   Source link path information.
   *
   * @return array
   *   The route information based on the source link_path.
   */
  protected function doTransform($value) {
    $pathValidator = $this->container->get('path.validator');
    $row = new Row();
    $migration = $this->prophesize(MigrationInterface::class)->reveal();
    $executable = $this->prophesize(MigrateExecutableInterface::class)->reveal();

    $plugin = new Route([], 'route', [], $migration, $pathValidator);
    $actual = $plugin->transform($value, $executable, $row, 'destination_property');
    return $actual;
  }

}
