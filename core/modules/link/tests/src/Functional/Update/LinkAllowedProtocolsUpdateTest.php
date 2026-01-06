<?php

declare(strict_types=1);

namespace Drupal\Tests\link\Functional\Update;

use Drupal\field\Entity\FieldConfig;
use PHPUnit\Framework\Attributes\Group;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for adding allowed_protocols to link fields.
 *
 * @see link_post_update_add_allowed_protocols()
 */
#[Group('Update')]
#[Group('link')]
class LinkAllowedProtocolsUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/link-field-without-allowed-protocols.php',
    ];
  }

  /**
   * Tests that the post_update adds allowed_protocols to link fields.
   */
  public function testUpdateLinkAllowedProtocols(): void {
    $config = $this->config('field.field.node.article.field_test_link');
    $settings = $config->get('settings');
    $this->assertArrayNotHasKey('allowed_protocols', $settings);

    $this->runUpdates();

    $field_config = FieldConfig::load('node.article.field_test_link');
    $settings = $field_config->get('settings');
    $this->assertArrayHasKey('allowed_protocols', $settings);
    $this->assertEquals([], $settings['allowed_protocols']);
  }

}
