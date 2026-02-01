<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme\Component;

use Drupal\Core\Render\Component\Exception\InvalidComponentException;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Component\ComponentMetadata;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the component metadata class.
 */
#[CoversClass(ComponentMetadata::class)]
#[Group('sdc')]
class ComponentMetadataTest extends UnitTestCase {

  /**
   * Tests that the correct data is returned for each property.
   */
  #[DataProvider('dataProviderMetadata')]
  public function testMetadata(array $metadata_info, array $expectations, bool $missing_schema): void {
    $metadata = new ComponentMetadata($metadata_info, 'foo/', FALSE);
    $this->assertSame($expectations['path'], $metadata->path);
    $this->assertSame($expectations['status'], $metadata->status);
    $this->assertSame($expectations['thumbnail'], $metadata->getThumbnailPath());
    $this->assertEquals($expectations['props'], $metadata->schema);
    $this->assertEquals($expectations['noUi'] ?? TRUE, $metadata->noUi);
  }

  /**
   * Tests the correct checks when enforcing schemas or not.
   */
  #[DataProvider('dataProviderMetadata')]
  public function testMetadataEnforceSchema(array $metadata_info, array $expectations, bool $missing_schema): void {
    if ($missing_schema) {
      $this->expectException(InvalidComponentException::class);
      $this->expectExceptionMessage('The component "' . $metadata_info['id'] . '" does not provide schema information. Schema definitions are mandatory for components declared in modules. For components declared in themes, schema definitions are only mandatory if the "enforce_prop_schemas" key is set to "true" in the theme info file.');
      new ComponentMetadata($metadata_info, 'foo/', TRUE);
    }
    else {
      new ComponentMetadata($metadata_info, 'foo/', TRUE);
      $this->expectNotToPerformAssertions();
    }
  }

  /**
   * Data provider for the test testMetadataEnforceSchema.
   *
   * @return array[]
   *   The batches of data.
   */
  public static function dataProviderMetadata(): array {
    return [
      'minimal example without schemas' => [
        [
          'path' => 'foo/bar/component-name',
          'id' => 'core:component-name',
          'name' => 'Component Name',
          'libraryOverrides' => ['dependencies' => ['core/drupal']],
          'group' => 'my-group',
          'description' => 'My description',
          'noUi' => TRUE,
        ],
        [
          'path' => 'bar/component-name',
          'status' => 'stable',
          'thumbnail' => '',
          'props' => NULL,
          'noUi' => TRUE,
        ],
        TRUE,
      ],
      'complete example with schema, but no meta:enum' => [
        [
          '$schema' => 'https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/assets/schemas/v1/metadata.schema.json',
          'id' => 'core:my-button',
          'machineName' => 'my-button',
          'path' => 'foo/my-other/path',
          'name' => 'Button',
          'description' => 'JavaScript enhanced button that tracks the number of times a user clicked it.',
          'libraryOverrides' => ['dependencies' => ['core/drupal']],
          'group' => 'my-group',
          'noUi' => FALSE,
          'props' => [
            'type' => 'object',
            'required' => ['text'],
            'properties' => [
              'text' => [
                'type' => 'string',
                'title' => 'Title',
                'description' => 'The title for the button',
                'minLength' => 2,
                'examples' => ['Press', 'Submit now'],
              ],
              'iconType' => [
                'type' => 'string',
                'title' => 'Icon Type',
                'enum' => [
                  'power',
                  'like',
                  'external',
                ],
              ],
            ],
          ],
        ],
        [
          'path' => 'my-other/path',
          'status' => 'stable',
          'thumbnail' => '',
          'group' => 'my-group',
          'additionalProperties' => FALSE,
          'noUi' => FALSE,
          'props' => [
            'type' => 'object',
            'required' => ['text'],
            'additionalProperties' => FALSE,
            'properties' => [
              'text' => [
                'type' => ['string', 'object'],
                'title' => 'Title',
                'description' => 'The title for the button',
                'minLength' => 2,
                'examples' => ['Press', 'Submit now'],
              ],
              'iconType' => [
                'type' => ['string', 'object'],
                'title' => 'Icon Type',
                'enum' => [
                  'power',
                  'like',
                  'external',
                ],
                'meta:enum' => [
                  'power' => new TranslatableMarkup('power', [], ['context' => '']),
                  'like' => new TranslatableMarkup('like', [], ['context' => '']),
                  'external' => new TranslatableMarkup('external', [], ['context' => '']),
                ],
              ],
            ],
          ],
        ],
        FALSE,
      ],
      'complete example with schema, but no matching meta:enum' => [
        [
          '$schema' => 'https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/assets/schemas/v1/metadata.schema.json',
          'id' => 'core:my-button',
          'machineName' => 'my-button',
          'path' => 'foo/my-other/path',
          'name' => 'Button',
          'description' => 'JavaScript enhanced button that tracks the number of times a user clicked it.',
          'libraryOverrides' => ['dependencies' => ['core/drupal']],
          'group' => 'my-group',
          'props' => [
            'type' => 'object',
            'required' => ['text'],
            'properties' => [
              'text' => [
                'type' => 'string',
                'title' => 'Title',
                'description' => 'The title for the button',
                'minLength' => 2,
                'examples' => ['Press', 'Submit now'],
              ],
              'iconType' => [
                'type' => 'string',
                'title' => 'Icon Type',
                'enum' => [
                  'power',
                  'like',
                  'external',
                ],
                'meta:enum' => [
                  'power' => 'Power',
                  'fav' => 'Favorite',
                  'external' => 'External',
                ],
              ],
            ],
          ],
        ],
        [
          'path' => 'my-other/path',
          'status' => 'stable',
          'thumbnail' => '',
          'group' => 'my-group',
          'additionalProperties' => FALSE,
          'noUi' => FALSE,
          'props' => [
            'type' => 'object',
            'required' => ['text'],
            'additionalProperties' => FALSE,
            'properties' => [
              'text' => [
                'type' => ['string', 'object'],
                'title' => 'Title',
                'description' => 'The title for the button',
                'minLength' => 2,
                'examples' => ['Press', 'Submit now'],
              ],
              'iconType' => [
                'type' => ['string', 'object'],
                'title' => 'Icon Type',
                'enum' => [
                  'power',
                  'like',
                  'external',
                ],
                'meta:enum' => [
                  'power' => new TranslatableMarkup('Power', [], ['context' => '']),
                  'like' => new TranslatableMarkup('like', [], ['context' => '']),
                  'external' => new TranslatableMarkup('External', [], ['context' => '']),
                ],
              ],
            ],
          ],
        ],
        FALSE,
      ],
      'complete example with schema, but no meta:enum, prop value not as string' => [
        [
          '$schema' => 'https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/assets/schemas/v1/metadata.schema.json',
          'id' => 'core:my-button',
          'machineName' => 'my-button',
          'path' => 'foo/my-other/path',
          'name' => 'Button',
          'description' => 'JavaScript enhanced button that tracks the number of times a user clicked it.',
          'libraryOverrides' => ['dependencies' => ['core/drupal']],
          'group' => 'my-group',
          'props' => [
            'type' => 'object',
            'required' => ['text'],
            'properties' => [
              'col' => [
                'type' => 'string',
                'title' => 'Column',
                'enum' => [
                  1,
                  2,
                  3,
                ],
              ],
            ],
          ],
        ],
        [
          'path' => 'my-other/path',
          'status' => 'stable',
          'thumbnail' => '',
          'group' => 'my-group',
          'additionalProperties' => FALSE,
          'noUi' => FALSE,
          'props' => [
            'type' => 'object',
            'required' => ['text'],
            'additionalProperties' => FALSE,
            'properties' => [
              'col' => [
                'type' => ['string', 'object'],
                'title' => 'Column',
                'enum' => [
                  1,
                  2,
                  3,
                ],
                'meta:enum' => [
                  1 => new TranslatableMarkup('1', [], ['context' => '']),
                  2 => new TranslatableMarkup('2', [], ['context' => '']),
                  3 => new TranslatableMarkup('3', [], ['context' => '']),
                ],
              ],
            ],
          ],
        ],
        FALSE,
      ],
      'complete example with schema (including meta:enum)' => [
        [
          '$schema' => 'https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/assets/schemas/v1/metadata.schema.json',
          'id' => 'core:my-button',
          'machineName' => 'my-button',
          'path' => 'foo/my-other/path',
          'name' => 'Button',
          'description' => 'JavaScript enhanced button that tracks the number of times a user clicked it.',
          'libraryOverrides' => ['dependencies' => ['core/drupal']],
          'group' => 'my-group',
          'props' => [
            'type' => 'object',
            'required' => ['text'],
            'properties' => [
              'text' => [
                'type' => 'string',
                'title' => 'Title',
                'description' => 'The title for the button',
                'minLength' => 2,
                'examples' => ['Press', 'Submit now'],
              ],
              'iconType' => [
                'type' => 'string',
                'title' => 'Icon Type',
                'enum' => [
                  'power',
                  'like',
                  'external',
                ],
                'meta:enum' => [
                  'power' => 'Power',
                  'like' => 'Like',
                  'external' => 'External',
                ],
              ],
            ],
          ],
        ],
        [
          'path' => 'my-other/path',
          'status' => 'stable',
          'thumbnail' => '',
          'group' => 'my-group',
          'additionalProperties' => FALSE,
          'noUi' => FALSE,
          'props' => [
            'type' => 'object',
            'required' => ['text'],
            'additionalProperties' => FALSE,
            'properties' => [
              'text' => [
                'type' => ['string', 'object'],
                'title' => 'Title',
                'description' => 'The title for the button',
                'minLength' => 2,
                'examples' => ['Press', 'Submit now'],
              ],
              'iconType' => [
                'type' => ['string', 'object'],
                'title' => 'Icon Type',
                'enum' => [
                  'power',
                  'like',
                  'external',
                ],
                'meta:enum' => [
                  'power' => new TranslatableMarkup('Power', [], ['context' => '']),
                  'like' => new TranslatableMarkup('Like', [], ['context' => '']),
                  'external' => new TranslatableMarkup('External', [], ['context' => '']),
                ],
              ],
            ],
          ],
        ],
        FALSE,
      ],
      'complete example with schema (including meta:enum and x-translation-context)' => [
        [
          '$schema' => 'https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/assets/schemas/v1/metadata.schema.json',
          'id' => 'core:my-button',
          'machineName' => 'my-button',
          'path' => 'foo/my-other/path',
          'name' => 'Button',
          'description' => 'JavaScript enhanced button that tracks the number of times a user clicked it.',
          'libraryOverrides' => ['dependencies' => ['core/drupal']],
          'group' => 'my-group',
          'props' => [
            'type' => 'object',
            'required' => ['text'],
            'properties' => [
              'text' => [
                'type' => 'string',
                'title' => 'Title',
                'description' => 'The title for the button',
                'minLength' => 2,
                'examples' => ['Press', 'Submit now'],
              ],
              'iconType' => [
                'type' => 'string',
                'title' => 'Icon Type',
                'enum' => [
                  'power',
                  'like',
                  'external',
                ],
                'meta:enum' => [
                  'power' => 'Power',
                  'like' => 'Like',
                  'external' => 'External',
                ],
                'x-translation-context' => 'Icon Type',
              ],
            ],
          ],
        ],
        [
          'path' => 'my-other/path',
          'status' => 'stable',
          'thumbnail' => '',
          'group' => 'my-group',
          'additionalProperties' => FALSE,
          'noUi' => FALSE,
          'props' => [
            'type' => 'object',
            'required' => ['text'],
            'additionalProperties' => FALSE,
            'properties' => [
              'text' => [
                'type' => ['string', 'object'],
                'title' => 'Title',
                'description' => 'The title for the button',
                'minLength' => 2,
                'examples' => ['Press', 'Submit now'],
              ],
              'iconType' => [
                'type' => ['string', 'object'],
                'title' => 'Icon Type',
                'enum' => [
                  'power',
                  'like',
                  'external',
                ],
                'meta:enum' => [
                  'power' => new TranslatableMarkup('Power', [], ['context' => 'Icon Type']),
                  'like' => new TranslatableMarkup('Like', [], ['context' => 'Icon Type']),
                  'external' => new TranslatableMarkup('External', [], ['context' => 'Icon Type']),
                ],
                'x-translation-context' => 'Icon Type',
              ],
            ],
          ],
        ],
        FALSE,
      ],
      'complete example with schema (including meta:enum and x-translation-context and an empty value)' => [
        [
          '$schema' => 'https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/assets/schemas/v1/metadata.schema.json',
          'id' => 'core:my-button',
          'machineName' => 'my-button',
          'path' => 'foo/my-other/path',
          'name' => 'Button',
          'description' => 'JavaScript enhanced button that tracks the number of times a user clicked it.',
          'libraryOverrides' => ['dependencies' => ['core/drupal']],
          'group' => 'my-group',
          'props' => [
            'type' => 'object',
            'required' => ['text'],
            'properties' => [
              'text' => [
                'type' => 'string',
                'title' => 'Title',
                'description' => 'The title for the button',
                'minLength' => 2,
                'examples' => ['Press', 'Submit now'],
              ],
              'target' => [
                'type' => 'string',
                'title' => 'Icon Type',
                'enum' => [
                  '',
                  '_blank',
                ],
                'meta:enum' => [
                  '' => 'Opens in same window',
                  '_blank' => 'Opens in new window',
                ],
                'x-translation-context' => 'Link target',
              ],
            ],
          ],
        ],
        [
          'path' => 'my-other/path',
          'status' => 'stable',
          'thumbnail' => '',
          'group' => 'my-group',
          'additionalProperties' => FALSE,
          'noUi' => FALSE,
          'props' => [
            'type' => 'object',
            'required' => ['text'],
            'additionalProperties' => FALSE,
            'properties' => [
              'text' => [
                'type' => ['string', 'object'],
                'title' => 'Title',
                'description' => 'The title for the button',
                'minLength' => 2,
                'examples' => ['Press', 'Submit now'],
              ],
              'target' => [
                'type' => ['string', 'object'],
                'title' => 'Icon Type',
                'enum' => [
                  '',
                  '_blank',
                ],
                'meta:enum' => [
                  '' => new TranslatableMarkup('Opens in same window', [], ['context' => 'Link target']),
                  '_blank' => new TranslatableMarkup('Opens in new window', [], ['context' => 'Link target']),
                ],
                'x-translation-context' => 'Link target',
              ],
            ],
          ],
        ],
        FALSE,
      ],
    ];
  }

  /**
   * Tests that getSchemaForUi() filters out props with noUi: true.
   */
  #[DataProvider('dataProviderSchemaForUi')]
  public function testGetSchemaForUi(array $metadata_info, ?array $expected_ui_schema): void {
    $metadata = new ComponentMetadata($metadata_info, 'foo/', FALSE);
    $this->assertEquals($expected_ui_schema, $metadata->getSchemaForUi());
  }

  /**
   * Data provider for the test testGetSchemaForUi.
   *
   * @return array[]
   *   The batches of data.
   */
  public static function dataProviderSchemaForUi(): array {
    return [
      'no schema returns null' => [
        [
          'path' => 'foo/bar/component-name',
          'id' => 'core:component-name',
          'name' => 'Component Name',
          'description' => 'Test component',
          'group' => 'Test Group',
        ],
        NULL,
      ],
      'props without noUi are all visible' => [
        [
          'path' => 'foo/bar/component-name',
          'id' => 'core:component-name',
          'name' => 'Component Name',
          'description' => 'Test component',
          'group' => 'Test Group',
          'props' => [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
              'title' => [
                'type' => 'string',
                'title' => 'Title',
              ],
              'description' => [
                'type' => 'string',
                'title' => 'Description',
              ],
            ],
          ],
        ],
        [
          'type' => 'object',
          'required' => ['title'],
          'additionalProperties' => FALSE,
          'properties' => [
            'title' => [
              'type' => ['string', 'object'],
              'title' => 'Title',
            ],
            'description' => [
              'type' => ['string', 'object'],
              'title' => 'Description',
            ],
          ],
        ],
      ],
      'props with noUi: true are filtered out' => [
        [
          'path' => 'foo/bar/component-name',
          'id' => 'core:component-name',
          'name' => 'Component Name',
          'description' => 'Test component',
          'group' => 'Test Group',
          'props' => [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
              'title' => [
                'type' => 'string',
                'title' => 'Title',
              ],
              'attributes' => [
                'type' => 'object',
                'title' => 'Attributes',
                'noUi' => TRUE,
                'default' => [],
              ],
              'utility_classes' => [
                'type' => 'array',
                'title' => 'Utility Classes',
                'noUi' => TRUE,
                'default' => [],
              ],
            ],
          ],
        ],
        [
          'type' => 'object',
          'required' => ['title'],
          'additionalProperties' => FALSE,
          'properties' => [
            'title' => [
              'type' => ['string', 'object'],
              'title' => 'Title',
            ],
          ],
        ],
      ],
      'props with noUi: false are visible' => [
        [
          'path' => 'foo/bar/component-name',
          'id' => 'core:component-name',
          'name' => 'Component Name',
          'description' => 'Test component',
          'group' => 'Test Group',
          'props' => [
            'type' => 'object',
            'properties' => [
              'title' => [
                'type' => 'string',
                'title' => 'Title',
                'noUi' => FALSE,
              ],
              'hidden_prop' => [
                'type' => 'string',
                'title' => 'Hidden Prop',
                'noUi' => TRUE,
              ],
            ],
          ],
        ],
        [
          'type' => 'object',
          'additionalProperties' => FALSE,
          'properties' => [
            'title' => [
              'type' => ['string', 'object'],
              'title' => 'Title',
              'noUi' => FALSE,
            ],
          ],
        ],
      ],
      'required array is filtered when noUi props are removed' => [
        [
          'path' => 'foo/bar/component-name',
          'id' => 'core:component-name',
          'name' => 'Component Name',
          'description' => 'Test component',
          'group' => 'Test Group',
          'props' => [
            'type' => 'object',
            'required' => ['title', 'attributes'],
            'properties' => [
              'title' => [
                'type' => 'string',
                'title' => 'Title',
              ],
              'attributes' => [
                'type' => 'object',
                'title' => 'Attributes',
                'noUi' => TRUE,
                'default' => [],
              ],
            ],
          ],
        ],
        [
          'type' => 'object',
          'required' => ['title'],
          'additionalProperties' => FALSE,
          'properties' => [
            'title' => [
              'type' => ['string', 'object'],
              'title' => 'Title',
            ],
          ],
        ],
      ],
      'required is removed when all required props have noUi' => [
        [
          'path' => 'foo/bar/component-name',
          'id' => 'core:component-name',
          'name' => 'Component Name',
          'description' => 'Test component',
          'group' => 'Test Group',
          'props' => [
            'type' => 'object',
            'required' => ['attributes'],
            'properties' => [
              'title' => [
                'type' => 'string',
                'title' => 'Title',
              ],
              'attributes' => [
                'type' => 'object',
                'title' => 'Attributes',
                'noUi' => TRUE,
                'default' => [],
              ],
            ],
          ],
        ],
        [
          'type' => 'object',
          'additionalProperties' => FALSE,
          'properties' => [
            'title' => [
              'type' => ['string', 'object'],
              'title' => 'Title',
            ],
          ],
        ],
      ],
      'all props hidden leaves empty properties' => [
        [
          'path' => 'foo/bar/component-name',
          'id' => 'core:component-name',
          'name' => 'Component Name',
          'description' => 'Test component',
          'group' => 'Test Group',
          'props' => [
            'type' => 'object',
            'properties' => [
              'attributes' => [
                'type' => 'object',
                'title' => 'Attributes',
                'noUi' => TRUE,
              ],
            ],
          ],
        ],
        [
          'type' => 'object',
          'additionalProperties' => FALSE,
          'properties' => [],
        ],
      ],
    ];
  }

}
