<?php

declare(strict_types=1);

namespace Drupal\Tests\ckeditor5\Kernel\ConfigAction;

use Drupal\Core\Recipe\RecipeRunner;
use Drupal\editor\Entity\Editor;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests config action 'setPluginConfiguration'.
 *
 * @covers \Drupal\ckeditor5\Plugin\ConfigAction\SetPluginConfiguration
 * @group ckeditor5
 * @group Recipe
 */
class SetPluginConfigurationConfigActionTest extends KernelTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ckeditor5',
    'editor',
    'filter',
    'filter_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    // This test must be allowed to save invalid config, we can confirm that
    // any invalid stuff is validated by the config actions system.
    'editor.editor.filter_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('filter_test');

    $editor = Editor::create([
      'editor' => 'ckeditor5',
      'format' => 'filter_test',
      'image_upload' => ['status' => FALSE],
    ]);
    $editor->save();

    /** @var array{toolbar: array{items: array<int, string>}} $settings */
    $settings = Editor::load('filter_test')?->getSettings();
    $this->assertSame(['heading', 'bold', 'italic'], $settings['toolbar']['items']);
  }

  /**
   * Tests setting ckeditor5_alignment configuration with config action.
   */
  public function testSetPluginConfiguration(): void {
    $recipe = $this->createRecipe([
      'name' => 'Sets plugin configuration',
      'config' => [
        'actions' => [
          'editor.editor.filter_test' => [
            'setPluginConfiguration' => [
              'id' => 'ckeditor5_alignment',
              'configuration' => [
                'enabled_alignments' => [
                  'left',
                  'right',
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    RecipeRunner::processRecipe($recipe);
    /** @var array{toolbar: array{items: array<int, string>}} $settings */
    $settings = Editor::load('filter_test')?->getSettings();
    $this->assertNotEmpty($settings['plugins']['ckeditor5_alignment']['enabled_alignments']);
    $this->assertSame(['left', 'right'], $settings['plugins']['ckeditor5_alignment']['enabled_alignments']);
  }

}
