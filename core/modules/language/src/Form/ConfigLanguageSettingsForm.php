<?php

declare(strict_types=1);

namespace Drupal\language\Form;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure language selector visibility for configuration entity forms.
 *
 * @internal
 */
class ConfigLanguageSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs a new ConfigLanguageSettingsForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'language_config_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['language.config_type_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('language.config_type_settings');
    $default_language_name = $this->languageManager->getDefaultLanguage()->getName();

    $langcode_configurable = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type instanceof ConfigEntityTypeInterface) {
        continue;
      }

      $label = (string) ($entity_type->getLabel() ?? $entity_type_id);
      if ($entity_type->isLangcodeConfigurable()) {
        $langcode_configurable[$entity_type_id] = $label;
      }
    }
    asort($langcode_configurable);

    $default_show_langcode = [];
    foreach ($langcode_configurable as $entity_type_id => $label) {
      if ((bool) $config->get('show_langcode.' . $entity_type_id)) {
        $default_show_langcode[] = $entity_type_id;
      }
    }

    $form['show_langcode'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allow creating configuration in languages other than the site default (@language_name)', ['@language_name' => $default_language_name]),
      '#options' => $langcode_configurable,
      '#default_value' => $default_show_langcode,
      '#description' => $this->t('Items not listed will always be created in the site default language.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('language.config_type_settings');
    $show_langcode = (array) $form_state->getValue('show_langcode', []);

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type instanceof ConfigEntityTypeInterface && $entity_type->isLangcodeConfigurable()) {
        $config->set('show_langcode.' . $entity_type_id, !empty($show_langcode[$entity_type_id]));
      }
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
