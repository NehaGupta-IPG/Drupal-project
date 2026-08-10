<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests validating referenceable entities.
 */
#[CoversMethod(DefaultSelection::class, 'validateReferenceableEntities')]
#[Group('entity_reference')]
#[RunTestsInSeparateProcesses]
class EntityReferenceSelectionValidateTest extends EntityKernelTestBase {

  /**
   * Tests that IDs an integer ID column cannot store are rejected.
   *
   * Such IDs never match an entity, but strict database backends throw an
   * exception when they are used in a query instead of returning no results.
   */
  #[DataProvider('providerUnstorableIds')]
  public function testUnstorableIds(string|int $id): void {
    $handler = $this->container->get('plugin.manager.entity_reference_selection')
      ->getInstance(['target_type' => 'entity_test']);

    $this->assertSame([], $handler->validateReferenceableEntities([$id]));
  }

  /**
   * Provides IDs that do not fit an unsigned integer ID column.
   *
   * @return array<string, array{string|int}>
   *   Test cases, keyed by a description of the ID.
   */
  public static function providerUnstorableIds(): array {
    return [
      'non-numeric string' => ['test'],
      'partially numeric string' => ['1test'],
      'empty string' => [''],
      'decimal' => ['1.5'],
      'negative' => ['-1'],
      'above the integer range' => ['2147483648'],
      'above the bigint range' => ['9223372036854775808'],
      'far above the bigint range' => ['99999999999999999999999999'],
    ];
  }

  /**
   * Tests that storable IDs are still validated against the database.
   */
  public function testStorableIds(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('entity_test');
    $entity = $storage->create(['name' => 'Test']);
    $entity->save();

    $handler = $this->container->get('plugin.manager.entity_reference_selection')
      ->getInstance(['target_type' => 'entity_test']);

    // Both string and integer IDs are accepted.
    $id = (int) $entity->id();
    $this->assertSame([$id => (string) $id], $handler->validateReferenceableEntities([$id]));
    $this->assertSame([$id => (string) $id], $handler->validateReferenceableEntities([(string) $id]));

    // An ID that fits the column but does not exist returns no results.
    $this->assertSame([], $handler->validateReferenceableEntities(['2147483647']));

    // Invalid IDs are filtered out without discarding the valid ones.
    $this->assertSame([$id => (string) $id], $handler->validateReferenceableEntities(['test', $id, '2147483648']));
  }

}
