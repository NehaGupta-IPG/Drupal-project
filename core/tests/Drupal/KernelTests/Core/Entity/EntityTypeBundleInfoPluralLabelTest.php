<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\entity_test\Entity\EntityTestBundleWithPluralLabels;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests bundle singular, plural and count labels.
 *
 * @coversDefaultClass \Drupal\Core\Entity\EntityTypeBundleInfo
 * @group Entity
 */
class EntityTypeBundleInfoPluralLabelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_bundle_test',
    'entity_test',
    'user',
  ];

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * The tested entity type ID.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The tested bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->bundleInfo = $this->container->get('entity_type.bundle.info');
  }

  /**
   * Tests singular, plural and count labels for bundles as config entities.
   *
   * @covers ::getAllBundleInfo
   */
  public function testLabelsForBundlesAsConfigEntities(): void {
    // Test bundles stored in config entities.
    $this->setTestingBundle('entity_test_with_bundle', 'article');

    /** @var \Drupal\Core\Config\Entity\EntityBundleWithPluralLabelsInterface $bundle_entity */
    $bundle_entity = EntityTestBundleWithPluralLabels::create([
      'id' => 'article',
      'label' => 'Article',
    ]);
    $bundle_entity->save();

    // Check the config entity getters.
    $this->assertNull($bundle_entity->getSingularLabel());
    $this->assertNull($bundle_entity->getPluralLabel());
    $this->assertNull($bundle_entity->getCountLabel());
    // Check that bundle info returns no label with bundle undefined labels.
    $this->assertSingularLabel(NULL);
    $this->assertPluralLabel(NULL);
    $this->assertCountLabel(1, 0, NULL);
    $this->assertCountLabel(100, 0, NULL);
    $this->assertCountLabel(1, 'search results', NULL);
    $this->assertCountLabel(100, 'search results', NULL);

    // Set singular, plural and count labels on the bundle entity.
    $bundle_entity
      ->setSingularLabel('article')
      ->setPluralLabel('articles')
      ->setCountLabel([
        "1 article\x03@count articles",
        'search results' => "1 article was found\x03@count articles were found",
      ])
      ->save();

    // Check the config entity getters.
    $this->assertSame('article', $bundle_entity->getSingularLabel());
    $this->assertSame('articles', $bundle_entity->getPluralLabel());
    $this->assertSame([
      "1 article\x03@count articles",
      'search results' => "1 article was found\x03@count articles were found",
    ], $bundle_entity->getCountLabel());
    // Check that labels are correctly returned by the bundle info service.
    $this->assertSingularLabel('article');
    $this->assertPluralLabel('articles');
    $this->assertCountLabel(1, 0, '1 article');
    $this->assertCountLabel(100, 0, '100 articles');
    $this->assertCountLabel(1, 'search results', '1 article was found');
    $this->assertCountLabel(100, 'search results', '100 articles were found');

    // Allow altering the labels via hook_entity_bundle_info_alter().
    // @see \entity_bundle_test_entity_bundle_info_alter()
    \Drupal::state()->set('entity_bundle_test.allow_alter', TRUE);
    // Also, clear the bundle info cache, to get fresh bundle definitions.
    $this->bundleInfo->clearCachedBundles();

    // Check that altered labels are returned by the bundle info service.
    $this->assertSingularLabel('article item');
    $this->assertPluralLabel('article items');
    $this->assertCountLabel(1, 0, '1 article item');
    $this->assertCountLabel(100, 0, '100 article items');
    $this->assertCountLabel(1, 'search results', '1 article item was found');
    $this->assertCountLabel(100, 'search results', '100 article items were found');
    // However, the getters are still showing the stored values.
    // @see https://www.drupal.org/project/drupal/issues/3186688
    $this->assertSame('article', $bundle_entity->getSingularLabel());
    $this->assertSame('articles', $bundle_entity->getPluralLabel());
    $this->assertSame([
      "1 article\x03@count articles",
      'search results' => "1 article was found\x03@count articles were found",
    ], $bundle_entity->getCountLabel());
  }

  /**
   * Tests singular, plural and count labels for bundles defined in code.
   *
   * @covers ::getAllBundleInfo
   */
  public function testLabelsForBundlesWithoutConfigEntities(): void {
    // Test entities with bundles not stored in config entities.
    // @see entity_bundle_test_entity_bundle_info()
    $this->setTestingBundle('entity_test', 'artist');

    // Check that labels are correctly returned.
    $this->assertSingularLabel('artist');
    $this->assertPluralLabel('artists');
    $this->assertCountLabel(1, 0, '1 artist');
    $this->assertCountLabel(100, 0, '100 artists');
    $this->assertCountLabel(1, 'search results', '1 artist was awarded');
    $this->assertCountLabel(100, 'search results', '100 artists were awarded');

    // Allow altering the labels via hook_entity_bundle_info_alter().
    // @see \entity_bundle_test_entity_bundle_info_alter()
    \Drupal::state()->set('entity_bundle_test.allow_alter', TRUE);
    // Also, clear the bundle info cache, to get fresh bundle definitions.
    $this->bundleInfo->clearCachedBundles();

    // Check that altered labels are correctly returned.
    $this->assertSingularLabel('creator');
    $this->assertPluralLabel('creators');
    $this->assertCountLabel(1, 0, '1 creator');
    $this->assertCountLabel(100, 0, '100 creators');
    $this->assertCountLabel(1, 'search results', '1 creator was awarded');
    $this->assertCountLabel(100, 'search results', '100 creators were awarded');
  }

  /**
   * Test passing a wrong entity type to ::getBundleCountLabel().
   *
   * @covers ::getBundleCountLabel
   */
  public function testGetBundleCountLabelWithWrongEntityType(): void {
    $this->expectExceptionObject(new \InvalidArgumentException("The 'nonexistent_entity_type' doesn't exist."));
    $this->bundleInfo->getBundleCountLabel('nonexistent_entity_type', 'article', 123, 'default');
  }

  /**
   * Test passing a wrong bundle type to ::getBundleCountLabel().
   *
   * @covers ::getBundleCountLabel
   */
  public function testGetBundleCountLabelWithWrongBundle(): void {
    $this->expectExceptionObject(new \InvalidArgumentException("The 'entity_test' entity type bundle 'nonexistent_bundle' doesn't exist."));
    $this->bundleInfo->getBundleCountLabel('entity_test', 'nonexistent_bundle', 123, 'default');
  }

  /**
   * Test passing a wrong bundle count label variant to ::getBundleCountLabel().
   *
   * @covers ::getBundleCountLabel
   */
  public function testGetBundleCountLabelWithWrongVariant(): void {
    EntityTestBundleWithPluralLabels::create([
      'id' => 'article',
      'label' => 'Article',
      'label_count' => [
        'default' => "1 item\x03@count items",
      ],
    ])->save();

    $this->expectExceptionObject(new \InvalidArgumentException("There's no variant 'nonexistent_variant' defined in label_count for bundle 'article' of 'entity_test_with_bundle' entity type."));
    $this->bundleInfo->getBundleCountLabel('entity_test_with_bundle', 'article', 123, 'nonexistent_variant');
  }

  /**
   * Asserts that a given bundle has an expected singular label.
   *
   * @param string|null $expected_singular_label
   *   The expected bundle singular label.
   */
  protected function assertSingularLabel(?string $expected_singular_label): void {
    $bundle_info = $this->bundleInfo->getBundleInfo($this->entityTypeId)[$this->bundle];
    $this->assertEquals($expected_singular_label, $bundle_info['label_singular']);
  }

  /**
   * Asserts that a given bundle has an expected plural label.
   *
   * @param string|null $expected_plural_label
   *   The expected bundle plural label.
   *
   * @throws \Exception
   */
  protected function assertPluralLabel(?string $expected_plural_label): void {
    $bundle_info = $this->bundleInfo->getBundleInfo($this->entityTypeId)[$this->bundle];
    $this->assertEquals($expected_plural_label, $bundle_info['label_plural']);
  }

  /**
   * Asserts an expected count label on a given bundle, count and variant.
   *
   * @param int $count
   *   The count.
   * @param string|int $variant
   *   The count label variant ID.
   * @param string|null $expected_count_label
   *   The expected count label.
   */
  protected function assertCountLabel(int $count, $variant, ?string $expected_count_label): void {
    $actual_count_label = $this->bundleInfo->getBundleCountLabel($this->entityTypeId, $this->bundle, $count, $variant);
    $this->assertEquals($expected_count_label, $actual_count_label);
  }

  /**
   * Sets the bundle being tested.
   *
   * @param string $entity_type_id
   *   The bundle's entity type ID.
   * @param string $bundle
   *   The bundle.
   */
  protected function setTestingBundle(string $entity_type_id, string $bundle): void {
    $this->entityTypeId = $entity_type_id;
    $this->bundle = $bundle;
  }

}
