<?php

namespace Drupal\config\Form;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Url;
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
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected StorageInterface $configStorage;

  /**
   * Constructs a new ConfigSingleImportForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The config storage.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    StorageInterface $config_storage
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configStorage = $config_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'config_single_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    string $config_type = '',
    string $config_name = ''
  ): array {

    $request = \Drupal::request();

    // Single source of truth:
    // 1. POST (HTMX)
    // 2. Route params
    $type = (string) (
      $request->request->get('config_type')
      ?? $config_type
    );

    $name = (string) (
      $request->request->get('config_name')
      ?? $config_name
    );

    // HTMX + Form API: no cache.
    $form['#cache']['max-age'] = 0;

    $form['#attributes']['id'] = 'config-export-form';

    /* ------------------------------------------------------------------
     * URL to push (PATH-BASED)
     * ------------------------------------------------------------------ */

    $route_params = array_filter([
      'config_type' => $type ?: NULL,
      'config_name' => $name ?: NULL,
    ]);

    $push_url = Url::fromRoute('config.export_single', $route_params)
      ->toString();

    /* ------------------------------------------------------------------
     * CONFIG TYPE
     * ------------------------------------------------------------------ */

    $configTypeOptions = $this->getConfigTypeOptions();

    $form['config_type'] = [
      '#title' => $this->t('Configuration type'),
      '#type' => 'select',
      '#options' => $configTypeOptions,
      '#default_value' => $type,
      '#attributes' => [
        'hx-post' => Url::fromRoute('config.export_single')->toString(),
        'hx-trigger' => 'change',
        'hx-include' => 'closest form',
        'hx-target' => '#config-export-form',
        'hx-select' => '#config-export-form',
        'hx-swap' => 'outerHTML'
      ],
    ];

    /* ------------------------------------------------------------------
     * CONFIG NAME
     * ------------------------------------------------------------------ */

    $configNameOptions = $type ? $this->findConfiguration($type) : [];

    // If name is not valid for current type → reset.
    if ($name && !isset($configNameOptions[$name])) {
      $name = '';
    }

    $form['config_name'] = [
      '#title' => $this->t('Configuration name'),
      '#type' => 'select',
      '#options' => $configNameOptions,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $name,
      '#attributes' => [
        'hx-post' => Url::fromRoute('config.export_single')->toString(),
        'hx-trigger' => 'change',
        'hx-include' => 'closest form',
        'hx-target' => '#config-export-form',
        'hx-select' => '#config-export-form',
        'hx-swap' => 'outerHTML'
      ],
    ];

    /* ------------------------------------------------------------------
     * EXPORT
     * ------------------------------------------------------------------ */

    $form['export'] = [
      '#title' => $this->t('Here is your configuration:'),
      '#type' => 'textarea',
      '#rows' => 24,
      '#value' => '',
    ];

    if ($type && $name) {
      $filename = $this->findFilename($type, $name);
      $form['export']['#value'] = $this->loadYaml($filename);
      $form['export']['#description'] = $filename;
    }

    $route_params = array_filter([
      'config_type' => $type ?: NULL,
      'config_name' => $name ?: NULL,
    ]);

    $push_url = Url::fromRoute('config.export_single', $route_params);

    (new Htmx())
      ->pushUrlHeader($push_url)
      ->applyTo($form);

    return $form;
  }

  /* ------------------------------------------------------------------
   * Helpers
   * ------------------------------------------------------------------ */

  protected function getConfigTypeOptions(): array {
    $options = [
      'system.simple' => $this->t('Simple configuration'),
    ];

    foreach ($this->entityTypeManager->getDefinitions() as $id => $definition) {
      if ($definition->entityClassImplements(ConfigEntityInterface::class)) {
        $options[$id] = $definition->getLabel()." (".$definition->id().")";
      }
    }

    return $options;
  }

  /**
   * Handles switching the configuration type selector.
   */
  protected function findConfiguration(string $type): array {
    // Handle simple configuration.
    if ($type === 'system.simple') {
      $all = $this->configStorage->listAll();
      $names = array_combine($all, $all);
      $options = [];

      foreach ($this->entityTypeManager->getDefinitions() as $entity_type => $definition) {
        if ($definition->entityClassImplements(ConfigEntityInterface::class)) {
          $options[$entity_type] = $definition;
        }
      }

      $config_prefixes = array_map(function (EntityTypeInterface $definition) {
        return $definition->getConfigPrefix() . '.';
      }, $options);

      // Find all config, and then filter our anything matching a config prefix.
      foreach ($names as $config_name) {
        foreach ($config_prefixes as $config_prefix) {
          if (str_starts_with($config_name, $config_prefix)) {
            unset($names[$config_name]);
          }
        }
      }
      return $names;
    }

    // Config entities.
    $names = [];
    $storage = $this->entityTypeManager->getStorage($type);

    // For a given entity type, load all entities.
    foreach ($storage->loadMultiple() as $entity) {
      $names[$entity->id()] = $entity->label() ? $entity->label()." (".$entity->id().")" : $entity->id();
    }

    return $names;
  }

  /**
   * Loads configuration data (yaml) for a given item.
   */
  protected function loadYaml(string $filename): string {
    if (!$this->configStorage->exists($filename)) {
      return '';
    }

    return Yaml::encode($this->configStorage->read($filename));
  }

  /**
   * Returns the filename of a configuration item.
   */
  protected function findFilename(string $type, string $name): string {
    if ($type !== 'system.simple') {
      $definition = $this->entityTypeManager->getDefinition($type);
      $name = $definition->getConfigPrefix() . '.' . $name;
    }

    if (!$this->configStorage->exists($name)) {
      return '';
    }

    return $name;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Nothing to submit.
  }

}
