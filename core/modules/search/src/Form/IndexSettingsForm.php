<?php

declare(strict_types=1);

namespace Drupal\search\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\search\Entity\SearchPage;
use Drupal\search\SearchIndexInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Search form.
 */
final class IndexSettingsForm extends FormBase {

  /**
   * The entities being listed.
   *
   * @var \Drupal\search\SearchPageInterface[]
   */
  protected array $entities = [];

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new SearchPageListBuilder object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\search\SearchIndexInterface $searchIndex
   *   The search index.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    MessengerInterface $messenger,
    protected SearchIndexInterface $searchIndex,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    $this->messenger = $messenger;
    $this->entities = SearchPage::loadMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): IndexSettingsForm {
    return new static(
      $container->get('messenger'),
      $container->get('search.index'),
      $container->get('module_handler'),

    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'search_index_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $search_settings = $this->config('search.settings');
    // Collect some stats.
    $remaining = 0;
    $total = 0;
    foreach ($this->entities as $entity) {
      if ($entity->isIndexable() && $status = $entity->getPlugin()->indexStatus()) {
        $remaining += $status['remaining'];
        $total += $status['total'];
      }
    }

    $this->moduleHandler->loadAllIncludes('admin.inc');
    $count = $this->formatPlural($remaining, 'There is 1 item left to index.', 'There are @count items left to index.');
    $done = $total - $remaining;
    // Use floor() to calculate the percentage, so if it is not quite 100%, it
    // will show as 99%, to indicate "almost done".
    $percentage = $total > 0 ? floor(100 * $done / $total) : 100;
    $percentage .= '%';
    $status = '<p><strong>' . $this->t('%percentage of the site has been indexed.', ['%percentage' => $percentage]) . ' ' . $count . '</strong></p>';
    $form['status'] = [
      '#type' => 'details',
      '#title' => $this->t('Indexing progress'),
      '#open' => TRUE,
      '#description' => $this->t('Only items in the index will appear in search results. To build and maintain the index, a correctly configured <a href=":cron">cron maintenance task</a> is required.', [':cron' => Url::fromRoute('system.cron_settings')->toString()]),
    ];
    $form['status']['status'] = ['#markup' => $status];
    $form['status']['wipe'] = [
      '#type' => 'submit',
      '#value' => $this->t('Re-index site'),
      '#submit' => ['::searchAdminReindexSubmit'],
    ];

    $items = [10, 20, 50, 100, 200, 500];
    $items = array_combine($items, $items);

    // Indexing throttle:
    $form['indexing_throttle'] = [
      '#type' => 'details',
      '#title' => $this->t('Indexing throttle'),
      '#open' => TRUE,
    ];
    $form['indexing_throttle']['cron_limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of items to index per run'),
      '#default_value' => $search_settings->get('index.cron_limit'),
      '#options' => $items,
      '#description' => $this->t('The maximum number of items processed per indexing run. If necessary, reduce the number of items to prevent timeouts and memory errors while indexing. Some search page types may have their own setting for this.'),
    ];
    // Indexing settings:
    $form['indexing_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Default indexing settings'),
      '#open' => TRUE,
      '#description' => $this->t('Changing these settings will cause the default search index to be rebuilt to reflect the new settings. Searching will continue to work, based on the existing index, but new content will not be indexed until all existing content has been re-indexed.'),
    ];
    $form['indexing_settings']['minimum_word_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum word length to index'),
      '#default_value' => $search_settings->get('index.minimum_word_size'),
      '#min' => 1,
      '#max' => 1000,
      '#description' => $this->t('The minimum character length for a word to be added to the index. Searches must include a keyword of at least this length.'),
    ];
    $form['indexing_settings']['overlap_cjk'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Simple CJK handling'),
      '#default_value' => $search_settings->get('index.overlap_cjk'),
      '#description' => $this->t('Whether to apply a simple Chinese/Japanese/Korean tokenizer based on overlapping sequences. Turn this off if you want to use an external preprocessor for this instead. Does not affect other languages.'),
    ];

    // Indexing settings:
    $form['logging'] = [
      '#type' => 'details',
      '#title' => $this->t('Logging'),
      '#open' => TRUE,
    ];

    $form['logging']['logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log searches'),
      '#default_value' => $search_settings->get('logging'),
      '#description' => $this->t('If checked, all searches will be logged. Uncheck to skip logging. Logging may affect performance.'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $search_settings = $this->configFactory->getEditable('search.settings');
    // If these settings change, the default index needs to be rebuilt.
    if (($search_settings->get('index.minimum_word_size') != $form_state->getValue('minimum_word_size')) || ($search_settings->get('index.overlap_cjk') != $form_state->getValue('overlap_cjk'))) {
      $search_settings->set('index.minimum_word_size', $form_state->getValue('minimum_word_size'));
      $search_settings->set('index.overlap_cjk', $form_state->getValue('overlap_cjk'));
      // Specifically mark items in the default index for reindexing, since
      // these settings are used in the SearchIndex::index() function.
      $this->messenger->addStatus($this->t('The default search index will be rebuilt.'));
      $this->searchIndex->markForReindex();
    }

    $search_settings
      ->set('index.cron_limit', $form_state->getValue('cron_limit'))
      ->set('logging', $form_state->getValue('logging'))
      ->save();

    $this->messenger->addStatus($this->t('The configuration options have been saved.'));
  }

  /**
   * Form submission handler for reindex button on search admin settings form.
   */
  public function searchAdminReindexSubmit(array &$form, FormStateInterface $form_state): void {
    // Send the user to the confirmation page.
    $form_state->setRedirect('search.reindex_confirm');
  }

}
