<?php

namespace Drupal\views\Plugin\views\area;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link as CoreLink;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsArea;

/**
 * Views area handler to display a link to an arbitrary URL.
 *
 * Useful, for example, to show a link to create content when a view has no
 * results.
 *
 * @ingroup views_area_handlers
 */
#[ViewsArea("link")]
class Link extends TokenizeAreaPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['path'] = ['default' => ''];
    $options['title'] = ['default' => ''];
    $options['class'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#description' => $this->t('An internal path such as %add-node, a URI such as %entity or %route, or an external URL such as %url. Enter %front to link to the front page.', [
        '%add-node' => '/node/add',
        '%entity' => 'entity:node/1',
        '%route' => 'route:user.login',
        '%url' => 'https://example.com',
        '%front' => '<front>',
      ]),
      '#default_value' => $this->options['path'],
      '#required' => TRUE,
    ];
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#default_value' => $this->options['title'],
      '#required' => TRUE,
    ];
    $form['class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS classes'),
      '#description' => $this->t('Separate multiple classes with spaces.'),
      '#default_value' => $this->options['class'],
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    $path = trim($form_state->getValue(['options', 'path']));
    // Paths containing tokens can only be validated at render time.
    if ($path === '' || str_contains($path, '{{') || str_contains($path, '[')) {
      return;
    }
    $url = $this->getUrl($path);
    try {
      // Generating the URL catches unknown routes and missing parameters.
      $url?->toString();
    }
    catch (\Exception) {
      $url = NULL;
    }
    if (!$url) {
      $form_state->setError($form['path'], $this->t('The URL %path is not valid.', ['%path' => $path]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    if ($empty && empty($this->options['empty'])) {
      return [];
    }

    // '<front>' would be removed as markup by the token replacement.
    $path = trim($this->options['path']) === '<front>' ? '<front>' : $this->tokenizePlainValue($this->options['path']);
    $title = $this->tokenizePlainValue($this->options['title']);
    if ($path === '' || $title === '' || !($url = $this->getUrl($path))) {
      return [];
    }

    // Do not show links the current user cannot follow.
    $access = $url->access(NULL, TRUE);
    $build = [];
    if ($access->isAllowed()) {
      $build = CoreLink::fromTextAndUrl($title, $url)->toRenderable();
      $classes = array_filter(array_map([Html::class, 'getClass'], explode(' ', $this->tokenizePlainValue($this->options['class']))));
      if ($classes) {
        $build['#attributes']['class'] = array_values($classes);
      }
    }
    CacheableMetadata::createFromObject($access)->applyTo($build);
    return $build;
  }

  /**
   * Tokenizes a value and converts the result to plain text.
   *
   * @param string $value
   *   The value to tokenize.
   *
   * @return string
   *   The tokenized value, without markup.
   */
  protected function tokenizePlainValue($value) {
    return trim(Html::decodeEntities(strip_tags((string) $this->tokenizeValue($value))));
  }

  /**
   * Builds a URL object from a user-entered path or URI.
   *
   * @param string $path
   *   An internal path, a URI with a scheme, or '<front>'.
   *
   * @return \Drupal\Core\Url|null
   *   The URL object, or NULL if the input could not be converted.
   */
  protected function getUrl($path) {
    if ($path === '<front>') {
      $path = 'route:<front>';
    }
    elseif (in_array($path[0], ['/', '?', '#'], TRUE)) {
      $path = 'internal:' . $path;
    }
    elseif (!parse_url($path, PHP_URL_SCHEME)) {
      return NULL;
    }
    try {
      return Url::fromUri($path);
    }
    catch (\InvalidArgumentException) {
      return NULL;
    }
  }

}
