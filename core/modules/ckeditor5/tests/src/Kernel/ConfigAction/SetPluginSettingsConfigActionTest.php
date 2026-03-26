<?php

declare(strict_types=1);

namespace Drupal\Tests\ckeditor5\Kernel\ConfigAction;

use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\editor\Entity\Editor;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\KernelTests\KernelTestBase;

/**
 * @covers \Drupal\ckeditor5\Plugin\ConfigAction\SetPluginSettings
 * @group ckeditor5
 * @group Recipe
 */
class SetPluginSettingsConfigActionTest extends KernelTestBase {

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
      'settings' => [
        'toolbar' => ['items' => ['alignment']],
        'plugins' => [
          'ckeditor5_alignment' => [
            'enabled_alignments' => ['left', 'right'],
          ],
        ],
      ],
    ]);
    $editor->save();
  }

  /**
   * Tests that plugin settings are updated correctly.
   */
  public function testSetPluginSettings(): void {
    $recipe = $this->createRecipe([
      'name' => 'Set plugin settings test',
      'config' => [
        'actions' => [
          'editor.editor.filter_test' => [
            'setPluginSettings' => [
              'plugin' => 'ckeditor5_alignment',
              'settings' => [
                'enabled_alignments' => ['center', 'left', 'right'],
              ],
            ],
          ],
        ],
      ],
    ]);
    RecipeRunner::processRecipe($recipe);

    /** @var array{plugins: array<string, array<mixed>>} $settings */
    $settings = Editor::load('filter_test')?->getSettings();
    $this->assertSame(['center', 'left', 'right'], $settings['plugins']['ckeditor5_alignment']['enabled_alignments']);
  }

  /**
   * Tests that settings are merged with existing plugin settings.
   */
  public function testSetPluginSettingsMergesWithExisting(): void {
    // First apply some settings.
    $recipe = $this->createRecipe([
      'name' => 'Set plugin settings merge test',
      'config' => [
        'actions' => [
          'editor.editor.filter_test' => [
            'setPluginSettings' => [
              'plugin' => 'ckeditor5_alignment',
              'settings' => [
                'enabled_alignments' => ['center'],
              ],
            ],
          ],
        ],
      ],
    ]);
    RecipeRunner::processRecipe($recipe);

    $settings = Editor::load('filter_test')?->getSettings();
    $this->assertSame(['center'], $settings['plugins']['ckeditor5_alignment']['enabled_alignments']);
  }

  /**
   * Tests that an error is thrown for a non-CKEditor 5 editor.
   */
  public function testActionRequiresCKEditor5(): void {
    $this->enableModules(['editor_test']);
    Editor::load('filter_test')?->setEditor('unicorn')->setSettings([])->save();

    $recipe = <<<YAML
name: Not a CKEditor
config:
  actions:
    editor.editor.filter_test:
      setPluginSettings:
        plugin: ckeditor5_alignment
        settings:
          enabled_alignments:
            - center
YAML;

    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage('The editor:setPluginSettings config action only works with editors that use CKEditor 5.');
    RecipeRunner::processRecipe($this->createRecipe($recipe));
  }

  /**
   * Tests that an error is thrown for a non-existent plugin.
   */
  public function testNonExistentPlugin(): void {
    $recipe = $this->createRecipe([
      'name' => 'Non-existent plugin test',
      'config' => [
        'actions' => [
          'editor.editor.filter_test' => [
            'setPluginSettings' => [
              'plugin' => 'ckeditor5_does_not_exist',
              'settings' => ['foo' => 'bar'],
            ],
          ],
        ],
      ],
    ]);

    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage('The editor:setPluginSettings config action cannot set settings for "ckeditor5_does_not_exist" because that CKEditor 5 plugin does not exist.');
    RecipeRunner::processRecipe($recipe);
  }

  /**
   * Tests that an error is thrown for a non-configurable plugin.
   */
  public function testNonConfigurablePlugin(): void {
    $recipe = $this->createRecipe([
      'name' => 'Non-configurable plugin test',
      'config' => [
        'actions' => [
          'editor.editor.filter_test' => [
            'setPluginSettings' => [
              'plugin' => 'ckeditor5_bold',
              'settings' => ['foo' => 'bar'],
            ],
          ],
        ],
      ],
    ]);

    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage('The editor:setPluginSettings config action cannot set settings for "ckeditor5_bold" because that CKEditor 5 plugin is not configurable.');
    RecipeRunner::processRecipe($recipe);
  }

  /**
   * Tests that an error is thrown for invalid value structure.
   */
  public function testInvalidValueStructure(): void {
    $recipe = $this->createRecipe([
      'name' => 'Invalid value test',
      'config' => [
        'actions' => [
          'editor.editor.filter_test' => [
            'setPluginSettings' => 'not-an-array',
          ],
        ],
      ],
    ]);

    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage('The editor:setPluginSettings config action requires an array with "plugin" and "settings" keys.');
    RecipeRunner::processRecipe($recipe);
  }

}
