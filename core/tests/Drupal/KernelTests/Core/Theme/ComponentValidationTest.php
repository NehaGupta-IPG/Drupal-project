<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Theme;

use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\Core\Plugin\Component;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests validation of single-directory components.
 */
#[Group('Theme')]
class ComponentValidationTest extends KernelTestBase {

  /**
   * Tests that optional SDC props with no value are still valid.
   */
  public function testOptionalProps(): void {
    $this->container->get(ThemeInstallerInterface::class)->install([
      'sdc_theme_test',
    ]);

    $component = $this->container->get(ComponentPluginManager::class)
      ->createInstance('sdc_theme_test:my-card');
    $this->assertInstanceOf(Component::class, $component);

    $build = [
      '#type' => 'component',
      '#component' => 'sdc_theme_test:my-card',
      '#props' => [
        'header' => 'This is clearly a header.',
        'slogan' => NULL,
        'author_and_time' => [
          'author' => NULL,
          'time' => 'January 1, 2038',
        ],
      ],
    ];
    $this->container->get(RendererInterface::class)->renderRoot($build);
  }

}
