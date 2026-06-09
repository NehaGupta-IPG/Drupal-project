<?php

namespace Drupal\Core\Menu\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a "Tabs" block to display the local tasks.
 */
#[Block(
  id: "local_tasks_block",
  admin_label: new TranslatableMarkup("Tabs")
)]
class LocalTasksBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The local task manager.
   *
   * @var \Drupal\Core\Menu\LocalTaskManagerInterface
   */
  protected $localTaskManager;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $translationManager;

  /**
   * The current_user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Creates a LocalTasksBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Menu\LocalTaskManagerInterface $local_task_manager
   *   The local task manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\StringTranslation\TranslationManager $translation_manager
   *   The translation manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LocalTaskManagerInterface $local_task_manager, RouteMatchInterface $route_match, LanguageManagerInterface $language_manager, #[Autowire(service: 'string_translation')] TranslationManager $translation_manager, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->localTaskManager = $local_task_manager;
    $this->routeMatch = $route_match;
    $this->languageManager = $language_manager;
    $this->translationManager = $translation_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'label_display' => '0',
      'primary' => TRUE,
      'secondary' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->configuration;
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($this->localTaskManager);
    // If the current route belongs to an entity, include cache tags of that
    // entity as well.
    $route_parameters = $this->routeMatch->getParameters()->all();
    foreach ($route_parameters as $parameter) {
      if ($parameter instanceof CacheableDependencyInterface) {
        $cacheability->addCacheableDependency($parameter);
      }
    }

    // Switch to the user's preferred admin language before resolving local task
    // titles. Unlike toolbar links, local task titles are cast to a plain
    // string inside LocalTaskManager::getTasksBuild(), so the language must be
    // active at call time rather than deferred to a #pre_render callback.
    $originalLangcode = NULL;
    $userAdminLangcode = $this->currentUser->getPreferredAdminLangcode(FALSE);
    if ($userAdminLangcode && ($this->currentUser->hasPermission('access administration pages') || $this->currentUser->hasPermission('view the administration theme'))) {
      $originalLangcode = $this->languageManager->getCurrentLanguage()->getId();
      $adminLanguage = $this->languageManager->getLanguage($userAdminLangcode);
      $this->languageManager->setCurrentLanguage($adminLanguage);
      $this->translationManager->setDefaultLangcode($userAdminLangcode);
      $this->languageManager->setConfigOverrideLanguage($adminLanguage);
    }

    $tabs = [
      '#theme' => 'menu_local_tasks',
    ];

    // Add only selected levels for the printed output.
    if ($config['primary']) {
      $links = $this->localTaskManager->getLocalTasks($this->routeMatch->getRouteName(), 0);
      $cacheability = $cacheability->merge($links['cacheability']);
      // Do not display single tabs.
      $tabs += [
        '#primary' => count(Element::getVisibleChildren($links['tabs'])) > 1 ? $links['tabs'] : [],
      ];
    }
    if ($config['secondary']) {
      $links = $this->localTaskManager->getLocalTasks($this->routeMatch->getRouteName(), 1);
      $cacheability = $cacheability->merge($links['cacheability']);
      // Do not display single tabs.
      $tabs += [
        '#secondary' => count(Element::getVisibleChildren($links['tabs'])) > 1 ? $links['tabs'] : [],
      ];
    }

    // Restore the original language after resolving titles.
    if ($originalLangcode !== NULL) {
      $originalLanguage = $this->languageManager->getLanguage($originalLangcode);
      $this->languageManager->setCurrentLanguage($originalLanguage);
      $this->translationManager->setDefaultLangcode($originalLangcode);
      $this->languageManager->setConfigOverrideLanguage($originalLanguage);
    }

    $build = [];
    $cacheability->addCacheContexts(['user.admin_language']);
    $cacheability->applyTo($build);
    if (empty($tabs['#primary']) && empty($tabs['#secondary'])) {
      return $build;
    }

    return $build + $tabs;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->configuration;
    $defaults = $this->defaultConfiguration();

    $form['levels'] = [
      '#type' => 'details',
      '#title' => $this->t('Shown tabs'),
      '#description' => $this->t('Select tabs being shown in the block'),
      // Open if not set to defaults.
      '#open' => $defaults['primary'] !== $config['primary'] || $defaults['secondary'] !== $config['secondary'],
    ];
    $form['levels']['primary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show primary tabs'),
      '#default_value' => $config['primary'],
    ];
    $form['levels']['secondary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show secondary tabs'),
      '#default_value' => $config['secondary'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $levels = $form_state->getValue('levels');
    $this->configuration['primary'] = $levels['primary'];
    $this->configuration['secondary'] = $levels['secondary'];
  }

  /**
   * {@inheritdoc}
   */
  public function createPlaceholder(): bool {
    return TRUE;
  }

}
