<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Components;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the JSON schema reference of the component plugin manager.
 */
#[Group('sdc')]
class SchemaReferenceResolverTest extends ComponentKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'sdc_test', 'sdc_test_replacements', 'image'];

  /**
   * {@inheritdoc}
   */
  protected static $themes = ['sdc_theme_test_references'];

  /**
   * Test that components props schemas are resolved correctly.
   */
  public function testReferenceResolver(): void {
    $component = $this->manager->find('sdc_theme_test_references:foo');
    $this->assertEquals($this->getExpectations(), $component->metadata->schema['properties']);
  }

  /**
   * Get expectations by manually "resolving" the references.
   *
   * @return array
   *   The resolved form of each prop.
   */
  protected function getExpectations(): array {
    $expectations = [];
    // $ref: module://image/schema.json#image
    $expectations['image'] = $this->getDataFromJson('module', 'image', 'schema.json')['image'];
    $system = $this->getDataFromJson('module', 'system', 'schema.json');
    // $ref: module://system/schema.json#attributes
    $expectations['attributes'] = $system['attributes'];
    // Test with nested references.
    // $ref: module://system/schema.json#links.
    $expectations['links'] = $system['links'];
    $expectations['links']['items'] = $system['link'];
    $expectations['links']['items']['properties']['attributes'] = $system['attributes'];
    $expectations['links']['items']['properties']['link_attributes'] = $system['attributes'];
    // Test from a theme.
    // $ref: theme://sdc_theme_test_references/bar.json#test.
    $expectations['from_theme'] = $this->getDataFromJson('theme', 'sdc_theme_test_references', 'bar.json')['test'];
    // Unresolvable: the $ref stays. It is how the schema resolver of
    // justinrainbow works.
    $expectations['unresolvable'] = [
      '$ref' => 'non-existing-schema://whatever',
    ];
    return $expectations;
  }

  /**
   * Get data from a JSON file.
   *
   * @param string $extension_type
   *   Extension type.
   * @param string $extension
   *   Extension ID.
   * @param string $path
   *   Path to the JSON file in the extension.
   *
   * @return array
   *   Data from the JSON file.
   */
  protected function getDataFromJson(string $extension_type, string $extension, string $path): array {
    $extensionList = \Drupal::service('extension.list.module');
    if ($extension_type == 'theme') {
      $extensionList = \Drupal::service('extension.list.theme');
    }
    $path = \Drupal::root() . '/' . $extensionList->getPath($extension) . '/' . $path;
    $content = file_get_contents($path);
    if ($content) {
      return json_decode($content, flags: JSON_OBJECT_AS_ARRAY);
    }
    return [];
  }

}
