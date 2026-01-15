<?php

namespace Drupal\Tests\layout_builder\Unit;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\layout_builder\Normalizer\SectionDataNormalizer;
use Drupal\layout_builder\Plugin\DataType\SectionData;
use Drupal\layout_builder\Section;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass SectionDataNormalizer
 * @group layout_builder
 */
class SectionDataNormalizerTest extends UnitTestCase {

  /**
   * The normalizer.
   *
   * @var \Drupal\layout_builder\Normalizer\SectionDataNormalizer
   */
  protected $normalizer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->normalizer = new SectionDataNormalizer();
  }

  /**
   * @covers ::supportsNormalization
   */
  public function testSupportsNormalization() {
    $section_data = $this->prophesize(SectionData::class);
    $this->assertTrue($this->normalizer->supportsNormalization($section_data->reveal()));
  }

  /**
   * @covers ::supportsDenormalization
   */
  public function testSupportsDenormalization() {
    $this->assertTrue($this->normalizer->supportsDenormalization([], SectionData::class));
  }

  /**
   * Tests the normalize function.
   *
   * @covers ::normalize
   *
   * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
   */
  public function testNormalize() {
    $data = ['foo'];
    $section_data = $this->prophesize(SectionData::class);
    $section = $this->prophesize(Section::class);
    $section->toArray()->willReturn($data);
    $section_data->getValue()->willReturn($section);
    $this->assertSame($data, $this->normalizer->normalize($section_data->reveal()));
  }

  /**
   * Tests the denormalize function.
   *
   * @covers ::denormalize
   *
   * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
   */
  public function testDenormalize() {
    $data = [
      'layout_id' => $this->randomMachineName(),
      'layout_settings' => [
        'label' => $this->randomMachineName(),
      ],
      'components' => [],
      'third_party_settings' => [],
    ];
    /** @var \Drupal\Core\Field\FieldItemInterface $target_instance */
    $target_instance = $this->prophesize(FieldItemInterface::class);
    $target_instance->getDataDefinition()->willReturn($this->prophesize(DataDefinitionInterface::class));
    $context = [
      'target_instance' => [
        $target_instance,
      ],
    ];
    /** @var \Drupal\layout_builder\Section $section_data */
    $section_data = $this->normalizer->denormalize($data, SectionData::class, $context);
    $this->assertEquals($data['layout_id'], $section_data->getLayoutId());
    $this->assertEquals($data['components'], $section_data->getComponents());
    foreach ($section_data->getThirdPartyProviders() as $provider) {
      $this->assertEquals($data['third_party_settings'], $section_data->getThirdPartySettings($provider));
    }
  }

}
