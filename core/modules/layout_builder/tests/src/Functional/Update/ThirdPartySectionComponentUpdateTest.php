<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the update path for section components.
 *
 * @group layout_builder
 * @group legacy
 */
class ThirdPartySectionComponentUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/layout-builder.php',
      __DIR__ . '/../../../fixtures/update/layout-builder-sections.php',
    ];
  }

  /**
   * Tests the update path for section components.
   */
  public function testRunUpdates(): void {
    $this->expectDeprecation('Setting additional properties is deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Additional component properties should be set via ::setThirdPartySetting(). See https://www.drupal.org/node/3100177');

    $display = \Drupal::entityTypeManager()->getStorage('entity_view_display')->load('node.article.teaser')->toArray();
    $before_section = $display['third_party_settings']['layout_builder']['sections'][0]->toArray();
    $this->assertEmpty($before_section['components']['2b3961a0-1c6f-4264-b01f-525a23e8c2b6']['additional']);
    $this->assertNotEmpty($before_section['components']['92bf8983-64cc-4f7d-b8c5-1ff9c6a5d7dc']['additional']);
    $this->assertEmpty($before_section['components']['2b3961a0-1c6f-4264-b01f-525a23e8c2b6']['third_party_settings']);
    $this->assertNotEmpty($before_section['components']['92bf8983-64cc-4f7d-b8c5-1ff9c6a5d7dc']['third_party_settings']);

    $this->runUpdates();

    $display = \Drupal::entityTypeManager()->getStorage('entity_view_display')->load('node.article.teaser')->toArray();
    $after_section = $display['third_party_settings']['layout_builder']['sections'][0]->toArray();
    $this->assertEmpty($after_section['components']['2b3961a0-1c6f-4264-b01f-525a23e8c2b6']['additional']);
    $this->assertNotEmpty($after_section['components']['92bf8983-64cc-4f7d-b8c5-1ff9c6a5d7dc']['additional']);
    $this->assertEquals($before_section['components']['92bf8983-64cc-4f7d-b8c5-1ff9c6a5d7dc']['additional'], $after_section['components']['92bf8983-64cc-4f7d-b8c5-1ff9c6a5d7dc']['additional']);
    $this->assertEmpty($after_section['components']['2b3961a0-1c6f-4264-b01f-525a23e8c2b6']['third_party_settings']);
    $this->assertNotEmpty($after_section['components']['92bf8983-64cc-4f7d-b8c5-1ff9c6a5d7dc']['third_party_settings']);
    $this->assertEquals($before_section['components']['92bf8983-64cc-4f7d-b8c5-1ff9c6a5d7dc']['third_party_settings'], $after_section['components']['92bf8983-64cc-4f7d-b8c5-1ff9c6a5d7dc']['third_party_settings']);
  }

}
