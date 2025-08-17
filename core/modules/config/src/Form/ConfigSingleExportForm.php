<?php

namespace Drupal\config\Form;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Htmx\HtmxSwapStyle;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for exporting a single configuration file.
 *
 * @internal
 */
class ConfigSingleExportForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * Tracks the valid config entity type definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected $definitions = [];

  /**
   * Constructs a new ConfigSingleImportForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The config storage.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, StorageInterface $config_storage) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configStorage = $config_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_single_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $config_type = NULL, $config_name = NULL) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type => $definition) {
      if ($definition->entityClassImplements(ConfigEntityInterface::class)) {
        $this->definitions[$entity_type] = $definition;
      }
    }
    $entity_types = array_map(function (EntityTypeInterface $definition) {
      return $definition->getLabel();
    }, $this->definitions);
    // Sort the entity types by label, then add the simple config to the top.
    uasort($entity_types, 'strnatcasecmp');
    $config_types = [
      'system.simple' => $this->t('Simple configuration'),
    ] + $entity_types;

    $config_type = $config_type ?? $form_state->getValue('config_type');
    $form['config_type'] = [
      '#title' => $this->t('Configuration type'),
      '#type' => 'select',
      '#options' => $config_types,
      '#default_value' => $config_type,
    ];
    (new Htmx())
      ->post()
      ->swap(HtmxSwapStyle::none)
      ->vals(['test' => 'test'])
      ->applyTo($form['config_type']);

    $config_name = $config_name ?? $form_state->getValue('config_name');
    $form['config_name'] = [
      '#title' => $this->t('Configuration name'),
      '#type' => 'select',
      '#options' => $this->findConfiguration($config_type),
      '#default_value' => $config_name,
    ];
    (new Htmx())
      ->post()
      ->swapOob()
      ->swap(HtmxSwapStyle::none)
      ->applyTo($form['config_name']);


    $form['export'] = [
      '#title' => $this->t('Here is your configuration:'),
      '#type' => 'textarea',
      '#rows' => 24,
      '#value' => '',
    ];
    (new Htmx())
      ->swapOob()
      ->applyTo($form['export']);
    if ($config_type && $config_name) {
      $fake_form_state = (new FormState())->setValues([
        'config_type' => $config_type,
        'config_name' => $config_name,
      ]);
      $form['export'] = $this->updateExport($form, $fake_form_state);
    }
    return $form;
  }

  /**
   * Handles switching the export textarea.
   */
  public function updateExport($form, FormStateInterface $form_state) {
    // Determine the full config name for the selected config entity.
    if ($form_state->getValue('config_type') !== 'system.simple') {
      $definition = $this->entityTypeManager->getDefinition($form_state->getValue('config_type'));
      $name = $definition->getConfigPrefix() . '.' . $form_state->getValue('config_name');
    }
    // The config name is used directly for simple configuration.
    else {
      $name = $form_state->getValue('config_name');
    }
    // Read the raw data for this config name, encode it, and display it.
    $exists = $this->configStorage->exists($name);
    $form['export']['#value'] = !$exists ? NULL : Yaml::encode($this->configStorage->read($name));
    $form['export']['#description'] = !$exists ? NULL : $this->t('Filename: %name', ['%name' => $name . '.yml']);
    return $form['export'];
  }

  /**
   * Handles switching the configuration type selector.
   */
  protected function findConfiguration($config_type) {
    $names = [
      '' => $this->t('- Select -'),
    ];
    // For a given entity type, load all entities.
    if ($config_type && $config_type !== 'system.simple') {
      $entity_storage = $this->entityTypeManager->getStorage($config_type);
      foreach ($entity_storage->loadMultiple() as $entity) {
        $entity_id = $entity->id();
        if ($label = $entity->label()) {
          $names[$entity_id] = new TranslatableMarkup('@id (@label)', ['@label' => $label, '@id' => $entity_id]);
        }
        else {
          $names[$entity_id] = $entity_id;
        }
      }
    }
    // Handle simple configuration.
    else {
      // Gather the config entity prefixes.
      $config_prefixes = array_map(function (EntityTypeInterface $definition) {
        return $definition->getConfigPrefix() . '.';
      }, $this->definitions);

      // Find all config, and then filter our anything matching a config prefix.
      $names += $this->configStorage->listAll();
      $names = array_combine($names, $names);
      foreach ($names as $config_name) {
        foreach ($config_prefixes as $config_prefix) {
          if (str_starts_with($config_name, $config_prefix)) {
            unset($names[$config_name]);
          }
        }
      }
    }
    return $names;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Nothing to submit.
  }

}
