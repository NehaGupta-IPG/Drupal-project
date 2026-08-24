<?php

namespace Drupal\views\Plugin\views\filter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\PluginBase;

/**
 * Provides filtering by language.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter("language")]
class LanguageFilter extends InOperator implements ContainerFactoryPluginInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Cached language options built from the languages used by the content.
   *
   * @var array|null
   */
  protected ?array $contentLanguages = NULL;

  /**
   * Constructs a new LanguageFilter instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct($configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    // When enabled and exposed, the filter lists only the languages that the
    // content actually uses (see ::valueForm()).
    $options['limit_to_content_languages'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);
    $form['limit_to_content_languages'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit to the languages used by the content'),
      '#description' => $this->t('When this filter is exposed, only list the languages that the content uses. Languages with no content, and options such as the site default or interface language, are not shown. The site default or interface language can be used as the default value and will display as the language it resolves to.'),
      '#default_value' => $this->options['limit_to_content_languages'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueTitle = $this->t('Language');
      // Pass the current values so options that are already selected do not get
      // lost when there are changes in the language configuration.
      $this->valueOptions = $this->listLanguages(LanguageInterface::STATE_ALL | LanguageInterface::STATE_SITE_DEFAULT | PluginBase::INCLUDE_NEGOTIATED, array_keys($this->value));
    }
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // No point in displaying the language filter on monolingual sites,
    // as only one language value is available.
    if (!$this->languageManager->isMultilingual()) {
      return;
    }

    // When limiting to the content's languages, do not apply a value (such as a
    // dynamic default that resolves to the interface language) when it points
    // to a language the content does not use; that value is not offered to
    // visitors, so the filter behaves as "- Any -" instead.
    if (!empty($this->options['limit_to_content_languages']) && !empty($this->options['exposed'])
      && !array_intersect_key($this->resolveLanguageValues($this->value), $this->getContentLanguageOptions())) {
      return;
    }

    parent::query();
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): bool {
    if (!$this->languageManager->isMultilingual() || !parent::access($account)) {
      return FALSE;
    }
    // When the options are limited to the languages the content uses, hide the
    // exposed filter entirely unless the content uses more than one language.
    if (!empty($this->options['limit_to_content_languages']) && !empty($this->options['exposed']) && $this->table && count($this->getContentLanguageOptions()) < 2) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    // The content-aware behavior only applies to the exposed form when the
    // option is enabled.
    if (empty($this->options['limit_to_content_languages']) || !$form_state->get('exposed')) {
      parent::valueForm($form, $form_state);
      return;
    }

    // Resolve a dynamic default value (such as the interface language) to the
    // actual language. Drop any value the content does not use.
    $content_options = $this->getContentLanguageOptions();
    $configured_value = $this->value;
    $this->value = array_intersect_key($this->resolveLanguageValues($this->value), $content_options);
    parent::valueForm($form, $form_state);
    // Restore the configured value so the query keeps using the dynamic
    // placeholder, which preserves the per-language cache contexts.
    $this->value = $configured_value;
    // Limit the visitor's choices to the languages that the content uses.
    if (isset($form['value']['#options'])) {
      $form['value']['#options'] = $content_options;
    }
  }

  /**
   * Resolves dynamic language placeholders to actual language codes.
   *
   * @param array $values
   *   Filter values that may contain dynamic placeholders, such as the site
   *   default or the negotiated interface language.
   *
   * @return array
   *   The values with any dynamic placeholders replaced by the language code
   *   they currently resolve to, keyed by value.
   */
  protected function resolveLanguageValues(array $values): array {
    $substitutions = static::queryLanguageSubstitutions();
    $resolved = [];
    foreach ($values as $value) {
      $value = $substitutions[$value] ?? $value;
      $resolved[$value] = $value;
    }
    return $resolved;
  }

  /**
   * Builds the language options from the languages used by the content.
   *
   * @return array
   *   Language names keyed by language code, limited to the languages present
   *   in the filter's table and ordered like the site's language list.
   */
  protected function getContentLanguageOptions(): array {
    if (!isset($this->contentLanguages)) {
      $present = $this->getContentLangcodes();
      // Label and order them like the site's language list (which includes the
      // locked "Not specified" and "Not applicable" languages). The labels are
      // built per request so they stay current with the language configuration.
      $this->contentLanguages = [];
      foreach ($this->languageManager->getLanguages(LanguageInterface::STATE_ALL) as $langcode => $language) {
        if (in_array($langcode, $present, TRUE)) {
          $this->contentLanguages[$langcode] = $language->getName();
        }
      }
    }
    return $this->contentLanguages;
  }

  /**
   * Gets the language codes the content uses.
   *
   * @return string[]
   *   The distinct language codes present in the filter's table.
   */
  protected function getContentLangcodes(): array {
    $entity_type = $this->view->getBaseEntityType();
    $tags = $entity_type ? $entity_type->getListCacheTags() : [];
    $cid = 'views:language_filter_content_langcodes:' . $this->table . ':' . $this->realField;
    $cache = \Drupal::cache();
    if ($tags && ($cached = $cache->get($cid))) {
      return $cached->data;
    }

    $query = \Drupal::database()->select($this->table, 'base');
    $query->addField('base', $this->realField, 'langcode');
    $query->distinct();
    $langcodes = $query->execute()->fetchCol();

    // Only cache when there is a way to invalidate it as the content changes.
    if ($tags) {
      $cache->set($cid, $langcodes, Cache::PERMANENT, $tags);
    }
    return $langcodes;
  }

}
