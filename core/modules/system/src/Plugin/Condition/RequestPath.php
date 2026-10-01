<?php

namespace Drupal\system\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\system\Path\PathPatternsMap;
use Drupal\system\Path\PathVisibilityMatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'Request Path' condition.
 */
#[Condition(
  id: "request_path",
  label: new TranslatableMarkup("Request Path"),
)]
class RequestPath extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * An alias manager to find the alias for the current system path.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * Constructs a RequestPath condition plugin.
   *
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   An alias manager to find the alias for the current system path.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\system\Path\PathVisibilityMatcher|null $visibilityMatcher
   *   The page patterns matcher.
   */
  public function __construct(AliasManagerInterface $alias_manager, PathMatcherInterface $path_matcher, RequestStack $request_stack, CurrentPathStack $current_path, array $configuration, $plugin_id, array $plugin_definition, protected ?PathVisibilityMatcher $visibilityMatcher = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->aliasManager = $alias_manager;
    $this->pathMatcher = $path_matcher;
    $this->requestStack = $request_stack;
    $this->currentPath = $current_path;
    if ($this->visibilityMatcher === NULL) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $visibilityMatcher argument is deprecated in drupal:11.5.0 and it will be required in drupal:13.0.0. See https://www.drupal.org/node/3589822', E_USER_DEPRECATED);
      $this->visibilityMatcher = \Drupal::service(PathVisibilityMatcher::class);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('path_alias.manager'),
      $container->get('path.matcher'),
      $container->get('request_stack'),
      $container->get('path.current'),
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(PathVisibilityMatcher::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['pages' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['pages'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pages'),
      '#default_value' => $this->configuration['pages'],
      '#description' => $this->t("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. An example path is %user-wildcard for every user page. %front is the front page.", [
        '%user-wildcard' => '/user/*',
        '%front' => '<front>',
      ]),
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $paths = array_map('trim', explode("\n", $form_state->getValue('pages')));
    foreach ($paths as $path) {
      if (empty($path) || $path === '<front>' || str_starts_with($path, '/')) {
        continue;
      }
      $form_state->setErrorByName('pages', $this->t("The path %path requires a leading forward slash when used with the Pages setting.", ['%path' => $path]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['pages'] = $form_state->getValue('pages');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    if (empty($this->configuration['pages'])) {
      return $this->t('No page is specified');
    }
    $pages = array_map('trim', explode("\n", $this->configuration['pages']));
    $pages = implode(', ', $pages);
    if (!empty($this->configuration['negate'])) {
      return $this->t('Do not return true on the following pages: @pages', ['@pages' => $pages]);
    }
    return $this->t('Return true on the following pages: @pages', ['@pages' => $pages]);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    // Convert path to lowercase. This allows comparison of the same path
    // with different case. Ex: /Page, /page, /PAGE.
    $pages = mb_strtolower($this->configuration['pages']);
    if (!$pages) {
      return TRUE;
    }
    return $this->visibilityMatcher->matches($pages);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    // Vary by the result of ::evaluate() rather than by path. Without page
    // patterns, ::evaluate() does not look at the path at all.
    $pages = mb_strtolower($this->configuration['pages']);
    if ($pages) {
      $contexts[] = 'url.path.matches:' . PathPatternsMap::hash($pages);
    }
    return $contexts;
  }

}
