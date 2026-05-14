<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Components;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the JSON schema reference of the component plugin manager.
 */
#[Group('sdc')]
#[RunTestsInSeparateProcesses]
class SchemaReferenceResolverTest extends ComponentKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sdc_test', 'sdc_test_replacements'];

  /**
   * {@inheritdoc}
   */
  protected static $themes = ['sdc_theme_test_references'];

  /**
   * Test that components props schemas are resolved correctly.
   */
  public function testReferenceResolver(): void {
    $definitions = $this->manager->getDefinitions();
    $definition = $definitions['sdc_theme_test_references:foo'];
    $this->assertEquals($this->getExpectations(), $definition['props']['properties']);
    // Unresolvable and required: the component is not loaded.
    $this->assertArrayNotHasKey('sdc_theme_test_references:invalid', $definitions);
    $this->assertArrayNotHasKey('sdc_theme_test_references:invalid_nested', $definitions);
  }

  /**
   * Get expectations by manually "resolving" the references.
   *
   * @return array
   *   The resolved form of each prop.
   */
  protected function getExpectations(): array {
    $expectations = [];
    $system = self::getDataFromJson('theme', 'sdc_theme_test_references', 'schema.json');
    // $ref: theme://image/schema.json#image
    $expectations['image'] = $system['image'];
    // $ref: theme://system/schema.json#attributes
    $expectations['attributes'] = $system['attributes'];
    // Test with nested references.
    // $ref: theme://system/schema.json#links.
    $expectations['links'] = $system['links'];
    $expectations['links']['items'] = $system['link'];
    $expectations['links']['items']['properties']['attributes'] = $system['attributes'];
    $expectations['links']['items']['properties']['link_attributes'] = $system['attributes'];
    // Unresolvable and optional: the component is loaded and the $ref stays.
    $expectations['unresolvable'] = [
      '$ref' => 'non-existing-schema://whatever',
    ];
    $expectations['recursive'] = [
      // Level 1.
      'type' => 'object',
      'properties' => [
        'foo' => [
          // Level 2.
          'type' => 'array',
          'items' => [
            // Level 3.
            'type' => 'object',
            'properties' => [
              'foo' => [
                // Level 4.
                'type' => 'array',
                'items' => [
                  // Level 5.
                  'type' => 'object',
                  'properties' => [
                    'foo' => [
                      // Level 6, bigger than MAXIMUM_RECURSION_LEVEL.
                      'type' => 'array',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
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
  protected static function getDataFromJson(string $extension_type, string $extension, string $path): array {
    $extensionList = \Drupal::service('extension.list.module');
    if ($extension_type == 'theme') {
      $extensionList = \Drupal::service('extension.list.theme');
    }
    $path = \Drupal::root() . '/' . $extensionList->getPath($extension) . '/' . $path;
    $content = file_get_contents($path);
    \assert(\is_string($content));
    return \json_decode($content, flags: JSON_OBJECT_AS_ARRAY);
  }

}
