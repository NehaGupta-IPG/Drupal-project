<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests third-party integration with the Section render array event system.
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

    // Check the 'main' region render array before altering.
    $region = $section_storage->getSection(0)->toRenderArray()['main'];
    $this->assertSame([
      'first-uuid',
      '#attributes',
    ], array_keys($region));
    $this->assertSame('Powered by Drupal', strip_tags($region['first-uuid']['content']['#markup']));

    // Activate regions altering by third-party and recreate the render array.
    // @see \Drupal\layout_builder_test\EventSubscriber\LayoutBuilderTestSubscriber::isSubscriberEnabled()
    $this->container->get('state')->set('layout_builder_test.subscriber.active', TRUE);
    $region = $section_storage->getSection(0)->toRenderArray()['main'];

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
  }

}
