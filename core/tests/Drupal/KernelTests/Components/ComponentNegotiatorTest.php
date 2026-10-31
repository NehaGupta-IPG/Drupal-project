<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Components;

use Drupal\Core\Theme\ComponentNegotiator;
use Drupal\Core\Theme\ExtensionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the component negotiator.
 */
#[CoversClass(ComponentNegotiator::class)]
#[Group('sdc')]
#[RunTestsInSeparateProcesses]
class ComponentNegotiatorTest extends ComponentKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sdc_test',
    'sdc_test_replacements',
  ];

  /**
   * Themes to install.
   *
   * @var string[]
   */
  protected static $themes = [
    'sdc_theme_test_enforce_schema', 'sdc_theme_test',
  ];

  /**
   * Tests negotiate.
   */
  public function testNegotiate(): void {
    $data = [
      ['sdc_test:my-banner', NULL],
      ['sdc_theme_test:my-card', 'sdc_theme_test_enforce_schema:my-card'],
      [
        'sdc_test:my-button',
        'sdc_test_replacements:my-button',
      ],
      ['invalid:component', NULL],
      ['invalid^component', NULL],
      ['', NULL],
    ];
    array_walk($data, function (array $test_input): void {
      [$requested_id, $expected_id] = $test_input;
      $negotiated_id = $this->negotiator->negotiate(
        $requested_id,
        $this->manager->getDefinitions(),
      );
      $this->assertSame($expected_id, $negotiated_id);
    });
  }

  /**
   * Tests that negotiate() caches null results.
   */
  public function testNegotiateCachesNullResults(): void {
    $definitions = $this->manager->getDefinitions();

    // sdc_test:my-banner has no replacement: negotiate() returns null.
    $this->assertNull($this->negotiator->negotiate('sdc_test:my-banner', $definitions));

    // Add a fake replacement and call again. The null result must be served
    // from cache. Definitions are immutable within a request, so this fake
    // entry only serves to detect if doNegotiate() re-ran unexpectedly.
    $definitions['fake:replacement'] = [
      'id' => 'fake:replacement',
      'replaces' => 'sdc_test:my-banner',
      'extension_type' => ExtensionType::Module,
      'provider' => 'sdc_test',
    ];
    $this->assertNull($this->negotiator->negotiate('sdc_test:my-banner', $definitions));
  }

  /**
   * Tests rendering components with component replacement.
   */
  public function testRenderWithReplacements(): void {
    $build = [
      '#type' => 'inline_template',
      '#template' => "{{ include('sdc_test:my-button') }}",
      '#context' => ['text' => 'Like!', 'iconType' => 'like'],
    ];
    $crawler = $this->renderComponentRenderArray($build);
    $this->assertNotEmpty($crawler->filter('#sdc-wrapper button[data-component-id="sdc_test_replacements:my-button"]'));
    $this->assertNotEmpty($crawler->filter('#sdc-wrapper button .sdc-id:contains("sdc_test_replacements:my-button")'));

    // Now test component replacement on themes.
    $build = [
      '#type' => 'inline_template',
      '#template' => "{{ include('sdc_theme_test:my-card') }}",
      '#context' => ['header' => 'Foo bar'],
      '#variant' => 'horizontal',
    ];
    $crawler = $this->renderComponentRenderArray($build);
    $this->assertNotEmpty($crawler->filter('#sdc-wrapper .component--my-card--replaced__body'));
  }

  /**
   * Tests that the default theme is used, not the active theme.
   */
  public function testNegotiateUsesDefaultTheme(): void {
    // Simulate an admin theme being active, while the replacement is provided
    // by the default theme.
    $this->container->get('theme_installer')->install(['stark']);
    $theme_manager = $this->container->get('theme.manager');
    $theme_manager->setActiveTheme($this->container->get('theme.initialization')->initTheme('stark'));

    $this->assertSame(
      'sdc_theme_test_enforce_schema:my-card',
      $this->negotiator->negotiate('sdc_theme_test:my-card', $this->manager->getDefinitions()),
    );
  }

  /**
   * Tests that passing the theme manager to the constructor is deprecated.
   */
  #[IgnoreDeprecations]
  public function testThemeManagerDeprecation(): void {
    $this->expectUserDeprecationMessage('Passing the theme.manager service to Drupal\Core\Theme\ComponentNegotiator::__construct() is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Pass the theme_handler service instead. See https://www.drupal.org/node/3549491');
    $negotiator = new ComponentNegotiator($this->container->get('theme.manager'));
    $this->assertSame(
      'sdc_theme_test_enforce_schema:my-card',
      $negotiator->negotiate('sdc_theme_test:my-card', $this->manager->getDefinitions()),
    );
  }

}
