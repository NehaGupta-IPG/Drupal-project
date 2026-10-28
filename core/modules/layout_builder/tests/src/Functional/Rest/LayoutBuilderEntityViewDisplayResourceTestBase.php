<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Functional\Rest;

use Drupal\FunctionalTests\Rest\EntityViewDisplayResourceTestBase;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Provides a base class for testing LayoutBuilderEntityViewDisplay resources.
 */
abstract class LayoutBuilderEntityViewDisplayResourceTestBase extends EntityViewDisplayResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['layout_builder'];

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    /** @var \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay $entity */
    $entity = parent::createEntity();
    $entity
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();
    $this->assertCount(1, $entity->getThirdPartySetting('layout_builder', 'sections'));
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    $expected = parent::getExpectedNormalizedEntity();
    array_unshift($expected['dependencies']['module'], 'layout_builder');
    $expected['hidden'][OverridesSectionStorage::FIELD_NAME] = TRUE;
    $expected['hidden']['links'] = TRUE;

    /** @var \Drupal\layout_builder\Section[] $sections */
    $sections = $this->entity->getThirdPartySetting('layout_builder', 'sections');
    $components = $sections[0]->getComponents();
    $component = array_pop($components);

    $expected['third_party_settings']['layout_builder'] = [
      'enabled' => TRUE,
      'allow_custom' => TRUE,
      'sections' => [
        [
          'layout_id' => 'layout_onecol',
          'layout_settings' => [
            'label' => '',
          ],
          'components' => [
            $component->getUuid() => [
              'uuid' => $component->getUuid(),
              'region' => 'content',
              'configuration' => [
                'label_display' => '0',
                'context_mapping' => [
                  'entity' => 'layout_builder.entity',
                ],
                'id' => 'extra_field_block:node:camelids:links',
              ],
              'weight' => 0,
              'additional' => [],
            ],
          ],
          'third_party_settings' => [],
        ],
      ],
    ];
    $expected['content'] = [];
    return $expected;
  }

}
