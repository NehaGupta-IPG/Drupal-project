<?php

namespace Drupal\search\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search\Form\SearchBlockForm;
use Drupal\search\SearchPageRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Search form' block.
 *
 * @property \Drupal\Core\Form\FormBuilderInterface $formBuilder
 * @property \Drupal\search\SearchPageRepositoryInterface $searchPageRepository
 */
#[Block(
  id: "search_form_block",
  admin_label: new TranslatableMarkup("Search form"),
  category: new TranslatableMarkup("Forms"),
)]
class SearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array|object $plugin_definition,
    protected \Closure|FormBuilderInterface $formBuilderClosure,
    protected \Closure|SearchPageRepositoryInterface $searchPageRepositoryClosure,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    if ($formBuilderClosure instanceof FormBuilderInterface) {
      @trigger_error('Passing a FormBuilderInterface object as the form builder argument in ' . __METHOD__ . 'is deprecated in drupal:11.3.0 and will not be supported in drupal:12.0.0. Pass a closure that returns the form builder instead. See https://www.drupal.org/node/3553889', E_USER_DEPRECATED);
      $this->formBuilderClosure = static fn(): FormBuilderInterface => \Drupal::service('form_builder');
    }
    if ($searchPageRepositoryClosure instanceof SearchPageRepositoryInterface) {
      @trigger_error('Passing a SearchPageRepositoryInterface object as the search page repository argument in ' . __METHOD__ . 'is deprecated in drupal:11.3.0 and will not be supported in drupal:12.0.0. Pass a closure that returns the search page repository instead. See https://www.drupal.org/node/3553889', E_USER_DEPRECATED);
      $this->searchPageRepositoryClosure = static fn(): SearchPageRepositoryInterface => \Drupal::service('search.search_page_repository');
    }
  }

  /**
   * Maintains backwards compatibility to service properties.
   */
  public function __get(string $name): mixed {
    // The $formBuilder and $searchPageRepository properties will be restored in
    // Drupal 12 using property hooks, but until then, provide backwards
    // compatibility for the properties for any subclasses by invoking the
    // respective closures.
    $closureName = $name . 'Closure';
    if (property_exists($this, $closureName)) {
      return ($this->$closureName)();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      fn(): FormBuilderInterface => $container->get('form_builder'),
      fn(): SearchPageRepositoryInterface => $container->get('search.search_page_repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'search content');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $page = $this->configuration['page_id'] ?? NULL;
    return $this->formBuilder->getForm(SearchBlockForm::class, $page);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'page_id' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    // The configuration for this block is which search page to connect the
    // form to. Options are all configured/active search pages.
    $options = [];
    $active_search_pages = $this->searchPageRepository->getActiveSearchPages();
    foreach ($this->searchPageRepository->sortSearchPages($active_search_pages) as $entity_id => $entity) {
      $options[$entity_id] = $entity->label();
    }

    $form['page_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Search page'),
      '#description' => $this->t('The search page that the form submits to, or Default for the default search page.'),
      '#default_value' => $this->configuration['page_id'],
      '#options' => $options,
      '#empty_option' => $this->t('Default'),
      '#empty_value' => '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Handle the #empty_value: using the default requires specifying `null` in
    // the config.
    // @see search.schema.yml
    // @see \Drupal\search\Form\SearchBlockForm::buildForm()
    $this->configuration['page_id'] = $form_state->getValue('page_id') ?: NULL;
  }

}
