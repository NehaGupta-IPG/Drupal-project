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
 */
#[Block(
  id: "search_form_block",
  admin_label: new TranslatableMarkup("Search form"),
  category: new TranslatableMarkup("Forms"),
)]
class SearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, string $plugin_id, array|object $plugin_definition, protected \Closure|FormBuilderInterface $formBuilder, protected \Closure|SearchPageRepositoryInterface $searchPageRepository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    if ($formBuilder instanceof FormBuilderInterface) {
      @trigger_error('Passing a FormBuilderInterface object as the form builder argument in ' . __METHOD__ . 'is deprecated in drupal:11.3.0 and will be removed in drupal:12.0.0. Pass a closure instead. See https://www.drupal.org/node/0000000', E_USER_DEPRECATED);
      $this->formBuilder = static fn(): FormBuilderInterface => \Drupal::service('form_builder');
    }
    if ($searchPageRepository instanceof SearchPageRepositoryInterface) {
      @trigger_error('Passing a SearchPageRepositoryInterface object as the search page repository argument in ' . __METHOD__ . 'is deprecated in drupal:11.3.0 and will be removed in drupal:12.0.0. Pass a closure instead. See https://www.drupal.org/node/0000000', E_USER_DEPRECATED);
      $this->formBuilder = static fn(): SearchPageRepositoryInterface => \Drupal::service('search.search_page_repository');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      static fn(): FormBuilderInterface => $container->get('form_builder'),
      static fn(): SearchPageRepositoryInterface => $container->get('search.search_page_repository')
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
    return ($this->formBuilder)()->getForm(SearchBlockForm::class, $page);
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
    $searchRepository = ($this->searchPageRepository)();
    $active_search_pages = $searchRepository->getActiveSearchPages();
    foreach ($searchRepository->sortSearchPages($active_search_pages) as $entity_id => $entity) {
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
