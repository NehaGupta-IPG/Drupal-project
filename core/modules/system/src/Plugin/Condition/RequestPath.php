<?php

namespace Drupal\system\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Provides a 'Request Path' condition.
 */
#[Condition(
  id: "request_path",
  label: new TranslatableMarkup("Request Path"),
)]
class RequestPath extends ConditionPluginBase {

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
   * phpcs:ignore Commenting.FunctionComment
   */
  #[Required]
  public function setAliasManager(AliasManagerInterface $aliasManager): void {
    $this->aliasManager = $aliasManager;
  }

  /**
   * phpcs:ignore Commenting.FunctionComment
   */
  #[Required]
  public function setPathMatcher(PathMatcherInterface $pathMatcher): void {
    $this->pathMatcher = $pathMatcher;
  }

  /**
   * phpcs:ignore Commenting.FunctionComment
   */
  #[Required]
  public function setRequestStack(RequestStack $requestStack): void {
    $this->requestStack = $requestStack;
  }

  /**
   * phpcs:ignore Commenting.FunctionComment
   */
  #[Required]
  public function setCurrentPath(CurrentPathStack $currentPath): void {
    $this->currentPath = $currentPath;
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

    $request = $this->requestStack->getCurrentRequest();
    // Compare the lowercase path alias (if any) and internal path.
    $path = $this->currentPath->getPath($request);
    // Do not trim a trailing slash if that is the complete path.
    $path = $path === '/' ? $path : rtrim($path, '/');
    $path_alias = mb_strtolower($this->aliasManager->getAliasByPath($path));

    return $this->pathMatcher->matchPath($path_alias, $pages) || (($path != $path_alias) && $this->pathMatcher->matchPath($path, $pages));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    $contexts[] = 'url.path';
    return $contexts;
  }

}
