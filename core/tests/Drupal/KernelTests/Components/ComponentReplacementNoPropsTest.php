<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Components;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests replacing a component that does not declare props.
 */
#[Group('sdc')]
#[RunTestsInSeparateProcesses]
class ComponentReplacementNoPropsTest extends ComponentKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sdc_test_replacements_no_props'];

  /**
   * {@inheritdoc}
   */
  protected static $themes = ['sdc_theme_test'];

  /**
   * Tests a component without props can be replaced by one with props.
   */
  public function testReplaceComponentWithoutProps(): void {
    $definitions = $this->manager->getDefinitions();
    $this->assertArrayNotHasKey('props', $definitions['sdc_theme_test:bar']);
    $this->assertSame('sdc_theme_test:bar', $definitions['sdc_test_replacements_no_props:bar']['replaces']);

    $this->assertSame(
      'sdc_test_replacements_no_props:bar',
      $this->negotiator->negotiate('sdc_theme_test:bar', $definitions),
    );

    $build = [
      '#type' => 'component',
      '#component' => 'sdc_theme_test:bar',
    ];
    $crawler = $this->renderComponentRenderArray($build);
    $this->assertSame('Bar', $crawler->filter('#sdc-wrapper h1.bar-replacement')->text());

    $build['#props'] = ['heading' => 'Replaced'];
    $crawler = $this->renderComponentRenderArray($build);
    $this->assertSame('Replaced', $crawler->filter('#sdc-wrapper h1.bar-replacement')->text());
  }

}
