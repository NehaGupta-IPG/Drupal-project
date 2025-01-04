<?php

declare(strict_types=1);

namespace Drupal\Tests\link\Kernel;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\link\LinkItemInterface;

/**
 * Tests post_update hook to add 'allowed_protocols' setting to all link fields.
 *
 * @group link
 */
class AddAllowedProtocolsPostUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'user',
    'entity_test',
    'link',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
  }

  /**
   * Tests that the post_update hook adds 'allowed_protocols' setting to all existing link fields.
   */
  public function testPostUpdateAddAllowedProtocols(): void {

    $link_type = LinkItemInterface::LINK_EXTERNAL;
    $field_name = $this->randomMachineName();

    // Create a field without the "allowed_protocols" setting.
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'link',
      'cardinality' => 1,
    ]);
    $field_storage->save();
    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'label' => 'Read more about this entity',
      'bundle' => 'entity_test',
      'settings' => [
        'title' => DRUPAL_OPTIONAL,
        'link_type' => $link_type,
      ],
    ]);
    $field_config->save();
    // We must remove manually the "allowed_protocols" setting to simulate the state
    // before the post_update since all new fields with the new code will have the setting.
    $config = \Drupal::configFactory()->getEditable('field.field.entity_test.entity_test.' . $field_name);
    $data = $config->get('settings');
    unset($data['allowed_protocols']);
    $config->set('settings', $data)->save();

    // Run the post_update hook.
    $sandbox = [];
    include_once \Drupal::service('extension.list.module')->getPath('link') . '/link.post_update.php';
    link_post_update_add_allowed_protocols($sandbox);

    // Reload the field configuration.
    $updated_field_config = FieldConfig::load($field_config->id());
    $settings = $updated_field_config->get('settings');

    // Check that the 'allowed_protocols' setting was added and has the correct default value.
    $this->assertArrayHasKey('allowed_protocols', $settings, "'allowed_protocols' was added.");
    $this->assertEquals([], $settings['allowed_protocols'], "'allowed_protocols' has the correct default value.");
  }

}
