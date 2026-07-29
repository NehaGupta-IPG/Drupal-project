<?php

namespace Drupal\Core\Condition;

use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;

/**
 * Provides common functionality for creating a conditions form.
 */
trait ConditionsFormTrait {

  /**
   * The key for the conditions form.
   *
   * @var string
   */
  protected string $conditionsFormKey = 'conditions';

  /**
   * The condition manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected ?ConditionManager $conditionManager = NULL;

  /**
   * Repository for context data.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected ?ContextRepositoryInterface $contextRepository = NULL;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected ?LanguageManagerInterface $languageManager = NULL;

  /**
   * Get the condition manager.
   *
   * @return \Drupal\Core\Condition\ConditionManager
   *   The condition manager.
   */
  protected function getConditionManager(): ConditionManager {
    if (!$this->conditionManager) {
      $this->conditionManager = \Drupal::service('plugin.manager.condition');
    }
    return $this->conditionManager;
  }

  /**
   * Get the context repository.
   *
   * @return \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   *   The context repository.
   */
  protected function getContextRepository(): ContextRepositoryInterface {
    if (!$this->contextRepository) {
      $this->contextRepository = \Drupal::service('context.repository');
    }
    return $this->contextRepository;
  }

  /**
   * Get the language manager.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   The language manager.
   */
  protected function getLanguageManager(): LanguageManagerInterface {
    if (!$this->languageManager) {
      $this->languageManager = \Drupal::service('language_manager');
    }
    return $this->languageManager;
  }

  /**
   * Conditions form constructor.
   *
   * Typically implemented in the buildForm() method, as follows:
   * `$form[$this->conditionsFormKey] = $this->buildConditionsForm(
   *   [],
   *   $form_state,
   *   'my_module',
   *   $conditions
   * );`
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $consumer
   *   A string identifying the consumer of the condition definitions (e.g.
   *   the module id).
   * @param array $conditions
   *   The conditions.
   *
   * @return array
   *   The form array with the visibility UI added in.
   */
  protected function buildConditionsForm(array $form, FormStateInterface $form_state, $consumer, $conditions): array {
    // Store the gathered contexts in the form_state, so it will be magically
    // submitted in our subforms:
    $form_state->setTemporaryValue('gathered_contexts', $this->getContextRepository()->getAvailableContexts());

    // Set conditions hierarchical, so form_state can properly read them.
    // Otherwise no conditions can be validated or submitted:
    $form['#tree'] = TRUE;
    // Build the conditions tabs:
    $form['condition_tabs'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Conditions'),
      '#description_display' => 'before',
      '#parents' => ['condition_tabs'],
    ];

    foreach ($this->getFilteredConditionDefinitions($consumer) as $conditionId => $filteredCondition) {
      // Don't display the language condition until we have multiple languages.
      if ($conditionId === 'language' && !$this->getLanguageManager()->isMultilingual()) {
        continue;
      }

      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $this->getConditionManager()->createInstance($conditionId, $conditions[$conditionId] ?? []);
      $form_state->set([$this->conditionsFormKey, $conditionId], $condition);
      $conditionForm = $condition->buildConfigurationForm([], $form_state);
      $conditionForm['#type'] = 'details';
      $conditionForm['#title'] = $condition->getPluginDefinition()['label'];
      $conditionForm['#group'] = 'condition_tabs';
      $form[$conditionId] = $conditionForm;
    }
    return $form;
  }

  /**
   * Retrieves filtered condition definitions for a given consumer.
   *
   * @param string $consumer
   *   A string identifying the consumer of the condition definitions (e.g.
   *   the module id).
   *
   * @return \Drupal\Component\Plugin\Definition\PluginDefinitionInterface[]
   *   An array of filtered condition definitions.
   */
  protected function getFilteredConditionDefinitions($consumer): array {
    return $this->getConditionManager()->getFilteredDefinitions(
      $consumer,
      $this->getContextRepository()->getAvailableContexts(),
    );
  }

  /**
   * Conditions form validation handler.
   *
   * Typically implemented in the validateForm() method as follows:
   * `$this->validateConditionsForm($form, $form_state);`
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function validateConditionsForm(array $form, FormStateInterface $form_state): void {
    // Validate visibility condition settings.
    foreach ($form_state->getValue($this->conditionsFormKey) as $conditionId => $values) {
      $subform = &$form[$this->conditionsFormKey][$conditionId];
      // All condition plugins use 'negate' as a Boolean in their schema.
      // However, certain form elements may return it as 0/1. Cast here to
      // ensure the data is in the expected type:
      if (array_key_exists('negate', $values)) {
        $form_state->setValue([$this->conditionsFormKey, $conditionId, 'negate'], (bool) $values['negate']);
      }
      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $form_state->get([$this->conditionsFormKey, $conditionId]);
      $condition->validateConfigurationForm($subform, SubformState::createForSubform($subform, $form, $form_state));
    }
  }

  /**
   * Conditions Form submission handler.
   *
   * Typically implemented in the submitForm() method. E.g.:
   * `$this
   *   ->config('my_module.settings')
   *   ->set(
   *     $this->conditionsFormKey,
   *     $this->submitConditionsForm($form, $form_state)
   * );`
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The conditions configuration.
   */
  protected function submitConditionsForm(array $form, FormStateInterface $form_state): array {
    $conditionsCollection = new ConditionPluginCollection($this->conditionManager, []);
    foreach ($form_state->getValue($this->conditionsFormKey) as $condition_id => $values) {
      $subform = &$form[$this->conditionsFormKey][$condition_id];
      // Allow the condition to submit the form.
      $condition = $form_state->get([$this->conditionsFormKey, $condition_id]);
      $condition->submitConfigurationForm($subform, SubformState::createForSubform($subform, $form, $form_state));

      $conditionConfig = $condition->getConfiguration();
      // Cast the negate value to a boolean, for consistency:
      $conditionConfig['negate'] = (bool) (array_key_exists('negate', $conditionConfig) ? $conditionConfig['negate'] : FALSE);
      // Add the condition to the collection:
      $conditionsCollection->addInstanceId($condition_id, $conditionConfig);
    }
    return $conditionsCollection->getConfiguration();
  }

}
