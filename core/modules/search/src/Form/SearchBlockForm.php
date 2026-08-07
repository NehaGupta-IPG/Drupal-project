<?php

namespace Drupal\search\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\search\SearchPageRepositoryInterface;

/**
 * Builds the search form for the search block.
 *
 * @internal
 */
class SearchBlockForm extends FormBase implements WorkspaceSafeFormInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  public function __construct(
    protected SearchPageRepositoryInterface $searchPageRepository,
    ConfigFactoryInterface $config_factory,
    protected RendererInterface $renderer,
  ) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_block_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_id = NULL) {
    // Set up the form to submit using GET to the correct search page.
    if (!$entity_id) {
      $entity_id = $this->searchPageRepository->getDefaultSearchPage();
      // SearchPageRepository::getDefaultSearchPage() depends on
      // search.settings.  The dependency needs to be added before the
      // conditional return, otherwise the block would get cached without the
      // necessary cacheability metadata in case there is no default search page
      // and would not be invalidated if that changes.
      $this->renderer->addCacheableDependency($form, $this->configFactory->get('search.settings'));
    }

    if (!$entity_id) {
      $form['message'] = [
        '#markup' => $this->t('Search is currently disabled'),
      ];
      return $form;
    }

    $route = 'search.view_' . $entity_id;
    $form['#action'] = Url::fromRoute($route)->toString();
    $form['#method'] = 'get';

    $form['keys'] = [
      '#type' => 'search',
      '#title' => $this->t('Search'),
      '#title_display' => 'invisible',
      '#size' => 15,
      '#default_value' => '',
      '#attributes' => ['title' => $this->t('Enter the terms you wish to search for.')],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      // Prevent op from showing up in the query string.
      '#name' => '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This form submits to the search page, so processing happens there.
  }

}
