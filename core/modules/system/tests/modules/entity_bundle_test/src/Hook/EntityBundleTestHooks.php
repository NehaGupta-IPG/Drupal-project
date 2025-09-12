<?php

declare(strict_types=1);

namespace Drupal\entity_bundle_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Module hook implementations.
 */
class EntityBundleTestHooks {

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  function setBundleEntityType(array &$entity_types): void {
    // Use 'entity_test_bundle_plural_labels' as bundle entity for
    // 'entity_test_with_bundle' in order to test bundle plural labels.
    // @see \Drupal\KernelTests\Core\Entity\EntityTypeBundleInfoPluralLabelTest
    $entity_types['entity_test_with_bundle']->set('bundle_entity_type', 'entity_test_bundle_plural_labels');
  }

  /**
   * Implements hook_entity_bundle_info().
   */
  #[Hook('entity_bundle_info')]
  function bundleInfo(): array {
    return [
      'entity_test' => [
        'artist' => [
          'label' => t('Artist'),
          'label_singular' => t('artist'),
          'label_plural' => t('artists'),
          'label_count' => [
            "1 artist\x03@count artists",
            'search results' => "1 artist was awarded\x03@count artists were awarded",
          ],
        ],
      ],
    ];
  }

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  function alterBundleInfo(array &$bundles): void {
    // Allow the test to trigger altering.
    // @see \Drupal\KernelTests\Core\Entity\EntityTypeBundleInfoPluralLabelTest
    if (\Drupal::state()->get('entity_bundle_test.allow_alter', FALSE)) {
      // Bundle defined via config entity.
      $bundles['entity_test_with_bundle']['article']['label_singular'] = 'article item';
      $bundles['entity_test_with_bundle']['article']['label_plural'] = 'article items';
      $bundles['entity_test_with_bundle']['article']['label_count'] = [
        "1 article item\x03@count article items",
        'search results' => "1 article item was found\x03@count article items were found",
      ];

      // Bundle defined via hook_entity_bundle_info().
      $bundles['entity_test']['artist']['label_singular'] = 'creator';
      $bundles['entity_test']['artist']['label_plural'] = 'creators';
      $bundles['entity_test']['artist']['label_count'] = [
        "1 creator\x03@count creators",
        'search results' => "1 creator was awarded\x03@count creators were awarded",
      ];
    }
  }

}
