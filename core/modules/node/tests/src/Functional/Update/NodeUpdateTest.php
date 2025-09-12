<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Tests node module updates.
 */
#[Group('node')]
#[IgnoreDeprecations]
#[CoversFunction('node_post_update_plural_variants')]
class NodeUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles[] = __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz';
  }

  /**
   * Tests node_post_update_plural_variants().
   */
  public function testPostUpdatePluralVariants(): void {
    $properties = ['label_singular', 'label_plural', 'label_count'];

    // Check that plural label variant properties are not present before update.
    $node_type = $this->config('node.type.page')->getRawData();
    foreach ($properties as $property) {
      $this->assertArrayNotHasKey($property, $node_type);
    }

    $this->runUpdates();

    // Check that plural label variant properties were added as NULL.
    $node_type = $this->config('node.type.page')->getRawData();
    foreach ($properties as $property) {
      $this->assertArrayHasKey($property, $node_type);
      $this->assertNull($node_type[$property]);
    }
  }

}
