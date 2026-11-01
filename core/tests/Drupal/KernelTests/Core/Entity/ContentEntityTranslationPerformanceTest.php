<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests performance of caching the original entity outside the save operation.
 *
 * @group Entity
 */
class ContentEntityTranslationPerformanceTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Enable 2 additional languages.
    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();

    $this->installEntitySchema('entity_test_mul');
  }

  /**
   * Tests that caching the original entity improves performance.
   */
  public function testTranslationChangesPerformance(): void {
    $user = $this->createUser();

    // Create a test entity.
    $entity = EntityTestMul::create([
      'name' => $this->randomString(),
      'user_id' => $user->id(),
      'language' => 'en',
    ]);
    $translation_fr = $entity->addTranslation('fr');
    $translation_es = $entity->addTranslation('es');

    // Trigger some translation changes.
    $translation_fr->set('name', 'fr-' . $this->randomString());
    $translation_fr->save();
    $translation_es->set('name', 'es-' . $this->randomString());
    $translation_es->save();

    // Step 1: Simulate unoptimized behavior.
    Database::startLog('testing_translation_performance');
    // Pass FALSE to not set the original on the entity.
    $this->performTranslationCheck($entity, FALSE);
    $unoptimized_queries = Database::getLog('testing_translation_performance');
    $unoptimized_query_count = count($unoptimized_queries);
    $unoptimized_time = $this->measureExecutionTime(function () use ($entity) {
      $this->performTranslationCheck($entity, FALSE);
    });

    // Step 2: Simulate optimized behavior.
    Database::startLog('testing_translation_performance');
    // Pass TRUE to cache the original on the entity.
    $this->performTranslationCheck($entity, TRUE);
    $optimized_queries = Database::getLog('testing_translation_performance');
    $optimized_query_count = count($optimized_queries);
    $optimized_time = $this->measureExecutionTime(function () use ($entity) {
      $this->performTranslationCheck($entity, TRUE);
    });

    // Caching the original on the entity has fewer database queries.
    $this->assertLessThan($unoptimized_query_count, $optimized_query_count, 'Caching the original in the entity results in fewer database queries.');

    // Caching the original on the entity is faster.
    $this->assertLessThan($unoptimized_time, $optimized_time, 'Caching the original in the entity results in faster execution time.');
  }

  /**
   * Helper function to test performance of hasTranslationChanges.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   * @param bool $use_optimized
   *   Whether to simulate the optimized behavior or not.
   */
  protected function performTranslationCheck(EntityInterface $entity, bool $use_optimized): void {
    // Simulate unoptimized by not caching the original entity.
    if (!$use_optimized) {
      $entity->original = NULL;
    }

    if ($use_optimized && !$entity->original) {
      $id = $entity->getOriginalId() ?? $entity->id();
      $entity->original = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId())->loadUnchanged($id);
    }

    foreach ($entity->getTranslationLanguages(FALSE) as $langcode => $language) {
      $translation = $entity->getTranslation($langcode);

      $translation->hasTranslationChanges();
    }
  }

  /**
   * Helper function to measure execution time of a callable.
   *
   * @param callable $function
   *   The callable function to measure.
   *
   * @return float
   *   The time taken to execute the function, in seconds.
   */
  protected function measureExecutionTime(callable $function): float {
    $start_time = microtime(TRUE);
    $function();
    return microtime(TRUE) - $start_time;
  }

}
