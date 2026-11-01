<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Kernel;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\EntityConstraintViolationList;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\jsonapi\Entity\EntityValidationTrait;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Routing\Routes;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests config write eligibility independently of entity storage.
 */
#[Group('jsonapi')]
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
class ConfigWriteValidationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'jsonapi_config_validation_test'];

  /**
   * Tests dynamically resolved schemas inside sequences and other plugins.
   */
  #[DataProvider('schemaCases')]
  public function testNestedSchema(string $plugin, ?string $child, ?string $invalid_path): void {
    $settings = $child === NULL ? ['label' => 'Example'] : ['child' => $child, 'settings' => ['label' => 'Example']];
    $data = $this->container->get('config.typed')->createFromNameAndData('jsonapi_config_validation_test.root', [
      'plugins' => [
        ['plugin' => $plugin, 'settings' => $settings],
      ],
    ]);
    $violations = new EntityConstraintViolationList($this->createMock(ConfigEntityInterface::class));
    $validator = new class() {
      use EntityValidationTrait;

      /**
       * Checks schema eligibility using the production validator.
       */
      public function check(TraversableTypedDataInterface $data, EntityConstraintViolationList $violations): void {
        self::validateConfigSchema($data, $violations);
      }

    };
    $validator->check($data, $violations);
    if ($invalid_path === NULL) {
      $this->assertCount(0, $violations);
    }
    else {
      $this->assertCount(1, $violations);
      $this->assertSame($invalid_path, $violations[0]->getPropertyPath());
    }
  }

  /**
   * Provides resolved schemas, including inherited eligibility.
   */
  public static function schemaCases(): array {
    return [
      'static sequence invalid plugin' => ['invalid', NULL, 'plugins.0.settings'],
      'static sequence valid plugin' => ['leaf', NULL, NULL],
      'inherited eligibility' => ['inherited', NULL, NULL],
      'valid outer invalid inner' => ['valid', 'invalid', 'plugins.0.settings.settings'],
      'valid outer valid inner' => ['valid', 'leaf', NULL],
      'valid outer inherited inner' => ['valid', 'inherited', NULL],
      'unknown plugin' => ['missing', NULL, 'plugins.0.settings'],
    ];
  }

  /**
   * Tests config routes never expose DELETE while allowing PATCH.
   */
  public function testConfigRoutes(): void {
    $resource_type = new ResourceType('date_format', 'date_format', DateFormat::class);
    $method = new \ReflectionMethod(Routes::class, 'getIndividualRoutesForResourceType');
    $routes = $method->invoke(NULL, $resource_type);
    $this->assertNotNull($routes->get('jsonapi.date_format--date_format.individual'));
    $this->assertNotNull($routes->get('jsonapi.date_format--date_format.individual.patch'));
    $this->assertNull($routes->get('jsonapi.date_format--date_format.individual.delete'));
  }

}
