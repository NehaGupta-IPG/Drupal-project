<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Block;

use Drupal\Component\Utility\Html;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests block theme suggestions and rendering.
 */
#[Group('block')]
#[RunTestsInSeparateProcesses]
class BlockTemplateSuggestionsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
  ];

  /**
   * Tests block suggestions and rendering with and without the Block module.
   */
  #[DataProvider('providerBlockModule')]
  public function testBlockThemeHookSuggestions(bool $block_module): void {
    if ($block_module) {
      $this->enableModules(['block']);
    }
    $this->installConfig(['system']);

    // Create a block using a plugin with derivative to be preprocessed.
    $plugin = $this->container->get('plugin.manager.block')->createInstance('system_menu_block:admin');
    $variables = [];
    $variables['elements']['#configuration'] = $plugin->getConfiguration();
    $variables['elements']['#plugin_id'] = $plugin->getPluginId();
    $variables['elements']['#id'] = 'machine_name';
    $variables['elements']['#base_plugin_id'] = $plugin->getBaseId();
    $variables['elements']['#derivative_plugin_id'] = $plugin->getDerivativeId();
    $variables['elements']['content'] = [];
    $module_handler = $this->container->get('module_handler');
    $suggestions = $module_handler->invokeAll('theme_suggestions_block', [$variables]);
    $this->assertSame([
      'block__system',
      'block__system_menu_block',
      'block__system_menu_block__admin',
      'block__machine_name',
    ], $suggestions);

    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    $build = $variables['elements'];
    $build['#theme'] = 'block';
    $build['#configuration']['label'] = 'Example block';
    $build['#configuration']['label_display'] = TRUE;
    $build['content'] = ['#plain_text' => 'Example <content>'];
    $output = (string) $this->container->get('renderer')->renderRoot($build);
    $xpath = new \DOMXPath(Html::load($output));
    $this->assertSame('Example block', $xpath->evaluate('string(//h2)'));
    $this->assertSame(1, $xpath->query('//nav[@id="block-machine-name"]')->length, $output);
    $this->assertStringContainsString('Example &lt;content&gt;', $output);
  }

  /**
   * Provides Block module installation states.
   */
  public static function providerBlockModule(): array {
    return [
      'without Block module' => [FALSE],
      'with Block module' => [TRUE],
    ];
  }

}
