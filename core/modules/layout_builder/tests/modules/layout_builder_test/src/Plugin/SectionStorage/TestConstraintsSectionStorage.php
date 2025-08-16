<?php

declare(strict_types=1);

namespace Drupal\layout_builder_test\Plugin\SectionStorage;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\Attribute\SectionStorage;
use Drupal\layout_builder\Plugin\SectionStorage\SectionStorageBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides a section storage that is always applicable but has constraints.
 */
#[SectionStorage(id: "layout_builder_test_constraints", context_definitions: [
  'value' => new ContextDefinition(
    data_type: 'string',
    constraints: [
      "Length" => [
        "min" => 5,
      ],
    ],
  ),
])]
class TestConstraintsSectionStorage extends SectionStorageBase {

  /**
   * {@inheritdoc}
   */
  public function isApplicable(RefinableCacheableDependencyInterface $cacheability) {
    $cacheability->mergeCacheMaxAge(0);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {}

  /**
   * {@inheritdoc}
   */
  protected function getSectionList() {}

  /**
   * {@inheritdoc}
   */
  public function getStorageId() {}

  /**
   * {@inheritdoc}
   */
  public function buildRoutes(RouteCollection $collection) {}

  /**
   * {@inheritdoc}
   */
  public function getRedirectUrl() {}

  /**
   * {@inheritdoc}
   */
  public function getLayoutBuilderUrl($rel = 'view') {}

  /**
   * {@inheritdoc}
   */
  public function deriveContextsFromRoute($value, $definition, $name, array $defaults) {}

  /**
   * {@inheritdoc}
   */
  public function label() {}

  /**
   * {@inheritdoc}
   */
  public function save() {}

}
