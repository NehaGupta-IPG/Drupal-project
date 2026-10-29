<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Unit\JsonApiResource;

use Drupal\jsonapi\JsonApiResource\Data;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\jsonapi\JsonApiResource\Data
 * @group jsonapi
 * @group legacy
 *
 * @internal
 */
class DataTest extends UnitTestCase {

  public function testCountDeprecation(): void {
    $mock = (new class([]) extends Data {
      protected $count;

      public function getTotalCount(): int {
        return parent::getTotalCount();
      }

      public function setTotalCount($count): void {
        parent::setTotalCount($count);
      }

    });

    $this->expectDeprecation(sprintf('The "%s::setTotalCount()" method is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use ResourceTypeBuildEvent::setCollectionSizeMemberName(\'count\') as a replacement. See https://www.drupal.org/node/3246951', Data::class));
    $count = 60;
    $mock->setTotalCount($count);

    $this->expectDeprecation(sprintf('The "%s::getTotalCount()" method is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use ResourceTypeBuildEvent::setCollectionSizeMemberName(\'count\') as a replacement. See https://www.drupal.org/node/3246951', Data::class));

    $this->assertEquals($count, $mock->getTotalCount());
  }

}
