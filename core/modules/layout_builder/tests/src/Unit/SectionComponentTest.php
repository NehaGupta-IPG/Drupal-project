<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Unit;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Layout\LayoutInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Prophecy\Argument;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests Drupal\layout_builder\SectionComponent.
 */
#[CoversClass(SectionComponent::class)]
#[Group('layout_builder')]
class SectionComponentTest extends UnitTestCase {

  /**
   * The section object to test.
   *
   * @var \Drupal\layout_builder\Section
   */
  protected $section;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $component = new SectionComponent(
      'some-uuid',
      'some-region',
      ['id' => 'existing-block-id'],
      [],
      [
        'Tech' => [
          'Bill Bob' => 'TPS reports',
          'Milton Adams' => 'Red Stapler',
        ],
        'Llama' => [
          'flair' => TRUE,
        ],
      ]
    );

    $this->section = new Section('layout_onecol', [], [$component]);
  }

  /**
   * Tests to render array.
   *
   * @legacy-covers ::toRenderArray
   */
  public function testToRenderArray(): void {
    $existing_block = $this->prophesize(BlockPluginInterface::class);
    $existing_block->getPluginId()->willReturn('block_plugin_id');

    $block_manager = $this->prophesize(BlockManagerInterface::class);
    $block_manager->createInstance('some_block_id', ['id' => 'some_block_id'])->willReturn($existing_block->reveal());

    // Imitate an event subscriber by setting a resulting build on the event.
    $event_dispatcher = $this->prophesize(EventDispatcherInterface::class);
    $event_dispatcher
      ->dispatch(Argument::type(SectionComponentBuildRenderArrayEvent::class), LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY)
      ->shouldBeCalled()
      ->will(function ($args) {
        /** @var \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event */
        $event = $args[0];
        $event->setBuild(['#markup' => $event->getPlugin()->getPluginId()]);
        return $event;
      });

    $layout_plugin = $this->prophesize(LayoutInterface::class);
    $layout_plugin->build(Argument::type('array'))->willReturnArgument(0);

    $layout_manager = $this->prophesize(LayoutPluginManagerInterface::class);
    $layout_manager->createInstance('layout_onecol', [])->willReturn($layout_plugin->reveal());

    $container = new ContainerBuilder();
    $container->set('plugin.manager.block', $block_manager->reveal());
    $container->set('event_dispatcher', $event_dispatcher->reveal());
    $container->set('plugin.manager.core.layout', $layout_manager->reveal());
    \Drupal::setContainer($container);

    $expected = [
      '#cache' => [
        'contexts' => [],
        'tags' => [],
        'max-age' => -1,
      ],
      '#markup' => 'block_plugin_id',
    ];

    $component = new SectionComponent('some-uuid', 'some-region', ['id' => 'some_block_id']);
    $result = $component->toRenderArray();
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests getThirdPartySettings.
   *
   * @legacy-covers ::getThirdPartySettings
   */
  #[DataProvider('providerTestGetThirdPartySettings')]
  public function testGetThirdPartySettings($provider, $expected): void {
    $this->assertSame($expected, $this->section->getComponent('some-uuid')->getThirdPartySettings($provider));
  }

  /**
   * Provides test data for ::testGetThirdPartySettings().
   *
   * @return array
   *   Third party settings.
   */
  public static function providerTestGetThirdPartySettings(): array {
    $data = [];
    $data['Tech third party settings'] = [
      'Tech',
      [
        'Bill Bob' => 'TPS reports',
        'Milton Adams' => 'Red Stapler',
      ],
    ];
    $data['Llama third party settings'] = [
      'Llama',
      ['flair' => TRUE],
    ];
    $data['Nonexisting provider'] = [
      'non_existing_provider',
      [],
    ];
    return $data;
  }

  /**
   * Tests getThirdPartySetting.
   *
   * @legacy-covers ::getThirdPartySetting
   */
  #[DataProvider('providerTestGetThirdPartySetting')]
  public function testGetThirdPartySetting($provider, $key, $expected, $default = FALSE): void {
    if ($default) {
      $this->assertSame($expected, $this->section->getComponent('some-uuid')->getThirdPartySetting($provider, $key, $default));
      return;
    }
    $this->assertSame($expected, $this->section->getComponent('some-uuid')->getThirdPartySetting($provider, $key));
  }

  /**
   * Provides test data for ::testGetThirdPartySetting().
   *
   * @return array
   *   Third party settings.
   */
  public static function providerTestGetThirdPartySetting(): array {
    $data = [];
    $data['Tech third party setting for "Bill Bob" key'] = [
      'Tech',
      'Bill Bob',
      'TPS reports',
    ];
    $data['Llama third party setting for "flair" key'] = [
      'Llama',
      'flair',
      TRUE,
    ];
    $data['Llama third party setting for nonexisting key'] = [
      'Llama',
      'non_existing_key',
      NULL,
    ];
    $data['Nonexisting provider third party setting for nonexisting key'] = [
      'non_existing_provider',
      'non_existing_key',
      NULL,
    ];
    $data['Nonexisting provider third party setting for nonexisting key with a default value provided'] = [
      'non_existing_provider',
      'non_existing_key',
      'default value',
      'default value',
    ];
    return $data;
  }

  /**
   * Tests setThirdPartySetting.
   *
   * @legacy-covers ::setThirdPartySetting
   */
  #[DataProvider('providerTestSetThirdPartySetting')]
  public function testSetThirdPartySetting($provider, $key, $value, $expected): void {
    $this->section->getComponent('some-uuid')->setThirdPartySetting($provider, $key, $value);
    $this->assertSame($expected, $this->section->getComponent('some-uuid')->getThirdPartySettings($provider));
  }

  /**
   * Provides test data for ::testSetThirdPartySettings().
   *
   * @return array
   *   Third party settings.
   */
  public static function providerTestSetThirdPartySetting(): array {
    $data = [];
    $data['Override "Milton Adams" third party setting for Tech provider'] = [
      'Tech',
      'Milton Adams',
      'Storage B',
      [
        'Bill Bob' => 'TPS reports',
        'Milton Adams' => 'Storage B',
      ],
    ];
    $data['Add "Peter Gibbons" third party setting for Tech provider'] = [
      'Tech',
      'Peter Gibbons',
      'Programmer',
      [
        'Bill Bob' => 'TPS reports',
        'Milton Adams' => 'Red Stapler',
        'Peter Gibbons' => 'Programmer',
      ],
    ];
    $data['Add "Medical Providers" provider third party settings'] = [
      'Medical Providers',
      'Dr. Swanson',
      'Hypnotist',
      [
        'Dr. Swanson' => 'Hypnotist',
      ],
    ];
    return $data;
  }

  /**
   * Tests unsetThirdPartySetting.
   *
   * @legacy-covers ::unsetThirdPartySetting
   */
  #[DataProvider('providerTestUnsetThirdPartySetting')]
  public function testUnsetThirdPartySetting($provider, $key, $expected): void {
    $this->section->getComponent('some-uuid')->unsetThirdPartySetting($provider, $key);
    $this->assertSame($expected, $this->section->getComponent('some-uuid')->getThirdPartySettings($provider));
  }

  /**
   * Provides test data for ::testUnsetThirdPartySetting().
   *
   * @return array
   *   Third party settings.
   */
  public static function providerTestUnsetThirdPartySetting(): array {
    $data = [];
    $data['Key with values'] = [
      'Tech',
      'Bill Bob',
      [
        'Milton Adams' => 'Red Stapler',
      ],
    ];
    $data['Key without values'] = [
      'Llama',
      'flair',
      [],
    ];
    $data['Non-existing key'] = [
      'Llama',
      'non_existing_key',
      [
        'flair' => TRUE,
      ],
    ];
    $data['Non-existing provider'] = [
      'non_existing_provider',
      'non_existing_key',
      [],
    ];
    return $data;
  }

  /**
   * Tests getThirdPartyProviders.
   *
   * @legacy-covers ::getThirdPartyProviders
   */
  public function testGetThirdPartyProviders(): void {
    $this->assertSame(['Tech', 'Llama'], $this->section->getComponent('some-uuid')->getThirdPartyProviders());
    $this->section->getComponent('some-uuid')->unsetThirdPartySetting('Llama', 'flair');
    $this->assertSame(['Tech'], $this->section->getComponent('some-uuid')->getThirdPartyProviders());
  }

  /**
   * Tests that deprecation notices are triggered.
   *
   * @todo Remove below test when the drupal:12.x branch is opened.
   * @see https://www.drupal.org/project/drupal/issues/3160644
   */
  #[IgnoreDeprecations]
  public function testDeprecationNotices(): void {
    $this->expectDeprecation('Setting random section component properties is deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Component properties should be set via dedicated setters. See https://www.drupal.org/node/3100177');
    $this->expectDeprecation('Getting additional properties is deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Additional component properties should be gotten via ::getThirdPartySetting(). See https://www.drupal.org/node/3100177');

    // Instantiate SectionComponent with additional settings is deprecated.
    new SectionComponent(
      'some-uuid',
      'some-region',
      [],
      // Provide deprecated 'additional' argument.
      [
        'spoiler-alert' => [
          'glitch-in-accounting' => 'fixed',
          'building-arson' => 'probably',
          'milton-on-beach' => TRUE,
        ],
      ],
      [],
    );

    // Instantiate SectionComponent object with preferred create() method.
    $component = new SectionComponent(
      'some-uuid',
      'some-region',
      [],
      [],
    );
    // Call deprecated set() and get() methods.
    $component->set('music', 'very 90s');
    $component->get('music');
  }

}
