<?php

declare(strict_types=1);

namespace Drupal\Tests\link\Functional;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a link widget that edits attributes keeps the ones it doesn't.
 */
#[Group('link')]
#[RunTestsInSeparateProcesses]
class LinkWidgetAttributesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'link', 'link_test_widget'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The stored attributes of the test link.
   */
  protected const STORED = [
    'class' => ['one', 'two'],
    'data-kept' => 'Set in code',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FieldStorageConfig::create([
      'field_name' => 'field_link',
      'entity_type' => 'entity_test',
      'type' => 'link',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_link',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('entity_test', 'entity_test')
      ->setComponent('field_link', ['type' => 'link_test_attribute'])
      ->save();

    $this->drupalLogin($this->drupalCreateUser([
      'administer entity_test content',
      'link to any page',
    ]));
  }

  /**
   * Tests that a widget's attribute elements replace and remove attributes.
   */
  public function testAttributeWidget(): void {
    $entity = EntityTest::create([
      'name' => 'Test',
      'field_link' => [
        'uri' => 'internal:/',
        'title' => 'Link',
        'options' => ['attributes' => self::STORED],
      ],
    ]);
    $entity->save();

    // Saving unchanged doesn't duplicate list values.
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm([], 'Save');
    $this->assertSame(self::STORED, $this->getAttributes($entity));

    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(['field_link[0][options][attributes][class]' => 'three'], 'Save');
    $this->assertSame(['class' => ['three'], 'data-kept' => 'Set in code'], $this->getAttributes($entity));

    $this->drupalGet($entity->toUrl('edit-form'));
    $this->submitForm(['field_link[0][options][attributes][class]' => ''], 'Save');
    $this->assertSame(['data-kept' => 'Set in code'], $this->getAttributes($entity));
  }

  /**
   * Gets the stored attributes of the entity's link.
   *
   * @param \Drupal\entity_test\Entity\EntityTest $entity
   *   The entity.
   *
   * @return array
   *   The attributes.
   */
  protected function getAttributes(EntityTest $entity): array {
    $storage = \Drupal::entityTypeManager()->getStorage('entity_test');
    $storage->resetCache([$entity->id()]);
    return $storage->load($entity->id())->get('field_link')->options['attributes'] ?? [];
  }

}
