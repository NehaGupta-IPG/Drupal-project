<?php

namespace Drupal\layout_builder\Context;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a wrapper around getting contexts from a section storage object.
 */
trait LayoutBuilderContextTrait {

  /**
   * The context repository.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Gets the context repository service.
   *
   * @return \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   *   The context repository service.
   */
  protected function contextRepository() {
    if (!$this->contextRepository) {
      $this->contextRepository = \Drupal::service('context.repository');
    }
    return $this->contextRepository;
  }

  /**
   * Returns all populated contexts, both global and section-storage-specific.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The section delta.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   The array of context objects.
   */
  protected function getPopulatedContexts(SectionStorageInterface $section_storage, int $delta = NULL): array {
    // Get all known globally available contexts IDs.
    $available_context_ids = array_keys($this->contextRepository()->getAvailableContexts());
    // Filter to those that are populated.
    $contexts = array_filter($this->contextRepository()->getRuntimeContexts($available_context_ids), function (ContextInterface $context) {
      return $context->hasContextValue();
    });

    // Add in the per-section_storage contexts.
    $contexts += $section_storage->getContextsDuringPreview();

    if ($delta !== NULL) {
      $contexts['section_delta'] = new Context(new ContextDefinition('integer'), $delta);
    }
    else {
      @trigger_error('Calling LayoutBuilderContextTrait::getPopulatedContexts() without the $delta argument is deprecated in drupal:9.3.0 and the $delta argument will be required in drupal:10.0.0. See https://www.drupal.org/node/3210520', E_USER_DEPRECATED);
    }

    return $contexts;
  }

}
