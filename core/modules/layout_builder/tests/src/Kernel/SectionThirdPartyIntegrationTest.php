<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Section;
use Drupal\layout_builder_test\Hook\LayoutBuilderTestSectionHooks;
use Drupal\layout_builder\SectionComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests third-party integration with the Section render array hook system.
 */
#[CoversClass(Section::class)]
#[Group('layout_builder')]
#[RunTestsInSeparateProcesses]
class SectionThirdPartyIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'layout_builder_test',
    'layout_discovery',
    'system',
    'user',
  ];

  /**
   * Tests third party integration.
   */
  public function testThirdPartyIntegration(): void {
    $section_data = [
      new Section(
        'layout_builder_test_plugin', [], [
          'first-uuid' => new SectionComponent(
            'first-uuid',
            'main',
            [
              'id' => 'system_powered_by_block',
            ]
          ),
        ],
      ),
    ];
    $section_storage = new TestSectionList($section_data);

    // Check the section render array before altering.
    $build = $section_storage->getSection(0)->toRenderArray();
    $this->assertArrayNotHasKey('third_party', $build);
    $region = $build['main'];
    $this->assertSame([
      'first-uuid',
      '#attributes',
    ], array_keys($region));
    $this->assertSame('Powered by Drupal', strip_tags($region['first-uuid']['content']['#markup']));

    // Activate regions altering by third-party and recreate the render array.
    $this->container->get(LayoutBuilderTestSectionHooks::class)->setActive(TRUE);
    $build = $section_storage->getSection(0)->toRenderArray();
    $region = $build['main'];

    // Check that that 'main' region render array has been altered.
    $this->assertSame([
      'before',
      'first-uuid',
      'after',
      '#attributes',
    ], array_keys($region));
    $this->assertSame('3rd party: before', $region['before']['#markup']);
    $this->assertSame('3rd party: replaced', $region['first-uuid']['content']['#markup']);
    $this->assertSame('3rd party: after', $region['after']['#markup']);

    // Check that the overall section render array has also been altered.
    $this->assertSame('3rd party: section', $build['third_party']['#markup']);
  }

}
