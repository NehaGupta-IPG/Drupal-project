<?php

namespace Drupal\link\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'link_autocomplete' widget.
 */
#[FieldWidget(
  id: 'link_autocomplete',
  label: new TranslatableMarkup('Link (with autocomplete)'),
  field_types: ['link'],
)]
class LinkAutocompleteWidget extends LinkWidget {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentUser = $container->get('current_user');
    $instance->entityRepository = $container->get('entity.repository');
    $instance->fileUrlGenerator = $container->get('file_url_generator');
    $instance->languageManager = $container->get('language_manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'auto_link_text' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['auto_link_text'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically populate link text from entity label'),
      '#default_value' => $this->getSetting('auto_link_text'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $auto_link_text = $this->getSetting('auto_link_text') ? $this->t('Yes') : $this->t('No');
    $summary[] = $this->t(
      'Automatically populate link text from entity label: @auto_link_text',
      ['@auto_link_text' => $auto_link_text]
    );

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    /** @var \Drupal\link\LinkItemInterface $item */
    $item = $items[$delta];
    $uri = $item->uri ?? NULL;

    $default_allowed = FALSE;
    $entity = NULL;

    try {
      // Try to fetch entity information from the URI.
      $default_allowed = !$item->isEmpty() && ($this->currentUser->hasPermission('link to any page') || $item->getUrl()->access());
    }
    catch (\InvalidArgumentException) {
      // Make sure we render the form if InvalidArgumentException is thrown.
    }

    // Try to load entity from stored metadata.
    if (!empty($item->options['data-entity-type']) && !empty($item->options['data-entity-uuid'])) {
      $entity = $this->entityRepository->loadEntityByUuid($item->options['data-entity-type'], $item->options['data-entity-uuid']);
    }
    else {
      // If no metadata, try to load entity from URI.
      // This prevents "Entity title (ID)" format from being displayed.
      $entity = $default_allowed && $uri ? $this->getEntityFromUri($uri) : NULL;
    }

    // Display entity URL consistently across all entity types.
    // This prevents the "Entity title (ID)" format that
    // getUriAsDisplayableString() would produce.
    if ($entity instanceof FileInterface) {
      // File entities are anomalies, so we handle them differently.
      $element['uri']['#default_value'] = $this->fileUrlGenerator->generateString($entity->getFileUri());
    }
    elseif ($entity instanceof EntityInterface) {
      $uri_parts = parse_url($uri);
      $uri_options = [];
      // Extract query parameters and fragment and merge them into $uri_options.
      if (isset($uri_parts['fragment']) && $uri_parts['fragment'] !== '') {
        $uri_options += ['fragment' => $uri_parts['fragment']];
      }
      if (!empty($uri_parts['query'])) {
        $uri_query = [];
        parse_str($uri_parts['query'], $uri_query);
        $uri_options['query'] = isset($uri_options['query']) ? $uri_options['query'] + $uri_query : $uri_query;
      }
      $element['uri']['#default_value'] = $entity->toUrl()->setOptions($uri_options)->toString();
    }
    elseif ($default_allowed && $uri && parse_url($uri, PHP_URL_SCHEME) !== 'entity') {
      // For non-entity URIs, use the displayable string from the parent.
      $element['uri']['#default_value'] = static::getUriAsDisplayableString($uri);
    }

    // Change the URI field to use textfield with autocomplete.
    $element['uri']['#type'] = 'textfield';
    // Preserve the parent's validation handler for manual URL entry.
    if (!isset($element['uri']['#element_validate'])) {
      $element['uri']['#element_validate'] = [];
    }
    if (!in_array([static::class, 'validateUriElement'], $element['uri']['#element_validate'], TRUE)) {
      $element['uri']['#element_validate'][] = [static::class, 'validateUriElement'];
    }
    $element['uri']['#description'] = $this->t('Start typing to find content and click on the suggestion below or paste a URL.');

    // Get host entity information for autocomplete.
    $host_entity_type_id = '';
    $host_entity_langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Get host entity from the field items.
    $host_entity = $items->getEntity();
    if ($host_entity instanceof EntityInterface) {
      $host_entity_type_id = $host_entity->getEntityTypeId();
      $host_entity_langcode = $host_entity->language()->getId();
    }

    // Build autocomplete URL with CSRF token.
    // The query parameters will be added dynamically by JavaScript.
    $autocomplete_url = Url::fromRoute('link.autocomplete');
    $generated_url = $autocomplete_url->toString(TRUE);
    // Render the URL to replace CSRF token placeholder.
    $url_with_csrf_token = [
      '#plain_text' => $generated_url->getGeneratedUrl(),
    ];
    $generated_url->applyTo($url_with_csrf_token);
    $autocomplete_url_string = (string) \Drupal::service('renderer')->renderInIsolation($url_with_csrf_token);

    // Add data attributes for JavaScript.
    $element['uri']['#attributes']['class'][] = 'link-autocomplete-widget-uri';
    $element['uri']['#attributes']['data-autocomplete-url'] = $autocomplete_url_string;
    $element['uri']['#attributes']['data-host-entity-type-id'] = $host_entity_type_id;
    $element['uri']['#attributes']['data-host-entity-langcode'] = $host_entity_langcode;

    // Add class to the URI fields item wrapper.
    $element['uri']['#wrapper_attributes']['class'][] = 'form-item--link-autocomplete-widget-uri';

    // Add a class to the title field and its item wrapper.
    $element['title']['#attributes']['class'][] = 'link-autocomplete-widget-title';
    $element['title']['#wrapper_attributes']['class'][] = 'form-item--link-autocomplete-widget-title';

    if ($this->getSetting('auto_link_text')) {
      $element['title']['#attributes']['data-autofill-enabled'] = TRUE;
    }

    // Add entity metadata as hidden fields.
    $element['attributes']['href'] = [
      '#type' => 'hidden',
      '#default_value' => $default_allowed ? ($uri ?? '') : '',
    ];
    $element['attributes']['data-entity-type'] = [
      '#type' => 'hidden',
      '#default_value' => $entity ? $entity->getEntityTypeId() : '',
    ];
    $element['attributes']['data-entity-uuid'] = [
      '#type' => 'hidden',
      '#default_value' => $entity ? $entity->uuid() : '',
    ];
    $element['attributes']['data-entity-substitution'] = [
      '#type' => 'hidden',
      '#default_value' => $entity ? ($entity->getEntityTypeId() === 'file' ? 'file' : 'canonical') : '',
    ];

    // Add custom library for the widget.
    $element['#attached']['library'][] = 'link/link.autocomplete_widget';
    // Add a custom class to the parent container.
    $element['#attributes']['class'][] = 'link-autocomplete-widget-container';
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$value) {
      // Process the URI from user input.
      // This handles entity resolution, external URLs, internal paths, etc.
      $value['uri'] = $this->uriFromUserInput($value['uri']);

      // Merge attributes into options.
      // This stores metadata at the top level of options:
      // options['data-entity-type'], etc.
      $value += ['options' => []];
      if (!empty($value['attributes'])) {
        // Preserve existing options structure (like 'attributes' for HTML
        // attributes).
        // Merge attributes into options, with attributes taking precedence.
        $value['options'] = array_merge($value['options'], $value['attributes']);
      }
    }
    return $values;
  }

  /**
   * Returns a processed URI with a proper scheme (if applicable).
   *
   * This method resolves paths to entities via URL routing instead of parsing
   * "Title (ID)" format. This works for all entity types, not just nodes.
   *
   * @param string $input
   *   The raw (or processed) input.
   *
   * @return string|null
   *   The URI string or null if the input is empty.
   */
  protected function uriFromUserInput($input) {
    if (empty($input)) {
      return NULL;
    }

    $host = parse_url($input, PHP_URL_HOST);
    $scheme = parse_url($input, PHP_URL_SCHEME);

    // Handle mailto links.
    if ($scheme == 'mailto') {
      return $input;
    }

    // Handle external URLs.
    if ($host && UrlHelper::isExternal($input)) {
      if (UrlHelper::externalIsLocal($input, \Drupal::request()->getSchemeAndHttpHost())) {
        // The link points to this domain. Make it relative to perform an entity
        // lookup.
        $host_end = strpos($input, $host) + strlen($host);
        $input = substr($input, $host_end);
      }
      else {
        // This link is really external.
        return $input;
      }
    }

    // Make sure the URI starts with a slash, otherwise the Url's factory
    // methods will throw exceptions.
    $starts_with_hash = strpos($input, '#') === 0;
    $starts_with_a_slash = strpos($input, '/') === 0;
    $is_front = substr($input, 0, 7) === '<front>';
    $is_nolink = substr($input, 0, 14) === 'route:<nolink>';
    if (!$scheme && !$is_front && !$is_nolink && !$starts_with_a_slash && !$starts_with_hash) {
      $input = "/$input";
    }
    // - '<front>' -> '/'
    // - '<front>#foo' -> '/#foo'
    if ($is_front) {
      $input = '/' . substr($input, strlen('<front>'));
    }

    // Try to resolve the path to an entity.
    $entity = $this->getEntityFromUserInput($input);
    if ($entity) {
      $query_and_fragment = $this->getQueryAndFragment($input);
      return 'entity:' . $entity->getEntityTypeId() . '/' . $entity->id() . $query_and_fragment;
    }

    // It's a relative link. If it's a file, store it as `base:`. Otherwise it's
    // most likely internal.
    $public_files_dir = \Drupal::service('stream_wrapper_manager')
      ->getViaScheme('public')
      ->getDirectoryPath();

    if (!empty($public_files_dir) && strpos($input, "/$public_files_dir") === 0) {
      return "base:$input";
    }
    $scheme = parse_url($input, PHP_URL_SCHEME);
    // Check if the input already contains a scheme.
    if (!empty($scheme)) {
      return $input;
    }

    // Support linking to nothing (same as parent class).
    if (in_array($input, ['<nolink>', '<none>', '<button>'], TRUE)) {
      return 'route:' . $input;
    }

    return "internal:$input";
  }

  /**
   * Tries to convert a URI into an entity in multiple ways.
   *
   * This resolves paths to entities via URL routing, which works for all
   * entity types.
   *
   * @param string $input
   *   A URI or a path.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity if found, null otherwise.
   */
  protected function getEntityFromUserInput($input) {
    $scheme = parse_url($input, PHP_URL_SCHEME);

    // Check if it's an entity URI (e.g. entity:node/1).
    if (($scheme === 'entity' || !$scheme) && ($entity = $this->getEntityFromUri($input))) {
      return $entity;
    }

    // If not, it can be a path pointing to an entity.
    if (!$scheme) {
      // Which can be hidden behind an alias in any of the site's languages.
      $input = 'internal:' . $this->getPathByAlias($input);
    }

    try {
      $route_name = Url::fromUri($input)->getRouteName();
      $params = array_filter(Url::fromUri($input)->getRouteParameters());
      foreach ($params as $possibly_an_entity_type => $possibly_an_entity_id) {
        // Return only the entity, if this is a canonical route.
        if ($route_name === 'entity.' . $possibly_an_entity_type . '.canonical') {
          $entity = $this->entityTypeManager->getStorage($possibly_an_entity_type)
            ->load($possibly_an_entity_id);
          if (!($entity instanceof EntityInterface)) {
            return NULL;
          }
          return $this->entityRepository->getTranslationFromContext($entity);
        }
      }
    }
    catch (\Exception) {
      // Or not.
    }

    return NULL;
  }

  /**
   * Load the entity referenced by an entity scheme URI.
   *
   * @param string $uri
   *   An internal URI string representing an entity path, such as
   *   "entity:node/23".
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The most appropriate translation of the entity that matches the given
   *   URI, or NULL if could not match any entity.
   */
  protected function getEntityFromUri($uri) {
    // Strip out potential query and fragment from the URI.
    $uri = strtok(strtok($uri, "?"), "#");

    // Reduce the URI to a "<entity type>/<id>" string. Only "entity:" URIs and
    // bare paths point at an entity; a URI with any other scheme (internal:,
    // route:, http:, ...) does not, so bail before parsing it as one.
    if (str_starts_with($uri, 'entity:')) {
      $uri = substr($uri, strlen('entity:'));
    }
    elseif (str_contains($uri, ':')) {
      return NULL;
    }
    else {
      $uri = trim($uri, '/');
    }

    if ($uri) {
      $parts = explode('/', $uri, 2);
      if (count($parts) === 2) {
        [$entity_type, $entity_id] = $parts;
        if ($this->entityTypeManager->hasDefinition($entity_type)) {
          if ($entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id)) {
            return $this->entityRepository->getTranslationFromContext($entity);
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Tries to translate the given raw URL path into an internal one.
   *
   * @param string $input
   *   Raw URL string consisting of a path and, optionally, query and fragment.
   *
   * @return string
   *   The internal path if any matched. The input string otherwise.
   */
  protected function getPathByAlias($input) {
    $config = \Drupal::config('language.negotiation');
    /** @var \Drupal\path_alias\AliasManagerInterface $path_alias_manager */
    $path_alias_manager = \Drupal::service('path_alias.manager');

    $input_path = parse_url($input, PHP_URL_PATH);
    foreach ($this->languageManager->getLanguages() as $language) {
      if ($prefix = $config->get('url.prefixes.' . $language->getId())) {
        // Strip the language prefix.
        $input_path = preg_replace("/^\/$prefix\//", '/', $input_path);
      }
      $path_resolved = $path_alias_manager->getPathByAlias($input_path, $language->getId());
      if ($path_resolved !== $input_path) {
        return $path_resolved . $this->getQueryAndFragment($input);
      }
    }

    return $input;
  }

  /**
   * Returns the query and fragment part of a given URL string.
   *
   * @param string $input
   *   An arbitrary URL.
   *
   * @return string
   *   The query and fragment parts or an empty string.
   */
  protected function getQueryAndFragment($input) {
    $result = '';
    if ($query = parse_url($input, PHP_URL_QUERY)) {
      $result .= "?$query";
    }
    if ($fragment = parse_url($input, PHP_URL_FRAGMENT)) {
      $result .= "#$fragment";
    }
    return $result;
  }

}
