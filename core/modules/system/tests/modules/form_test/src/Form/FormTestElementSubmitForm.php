<?php

declare(strict_types=1);

namespace Drupal\form_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\form_test\Callbacks;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for testing element-level submit callbacks.
 */
class FormTestElementSubmitForm extends FormBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * FormTestElementSubmitForm constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('messenger'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_element_submit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $object = new Callbacks();

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => 'Name',
      '#default_value' => '',
      '#element_submit' => [[$object, 'submitElement']],
    ];
    $form['group_1'] = [
      '#type' => 'details',
      '#tree' => TRUE,
      '#element_submit' => [[$object, 'submitElement']],
    ];
    $form['group_1']['name_1'] = [
      '#type' => 'textfield',
      '#title' => 'Name 1',
      '#default_value' => '',
      '#element_submit' => [[$object, 'submitElement']],
    ];
    $form['group_1']['name_2'] = [
      '#type' => 'textfield',
      '#title' => 'Name 2',
      '#default_value' => '',
      '#element_submit' => [[$object, 'submitElement']],
    ];
    $form['group_1']['submit_1'] = [
      '#type' => 'submit',
      '#value' => 'Save group 1',
      '#limit_element_submit' => [['group_1']],
      '#submit' => [[$this, 'submitForm1']],
    ];
    $form['group_2'] = [
      '#type' => 'details',
      '#tree' => TRUE,
    ];
    $form['group_2']['name_3'] = [
      '#type' => 'textfield',
      '#title' => 'Name 3',
      '#element_submit' => [[$object, 'submitElement']],
      '#default_value' => '',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Save',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('name') === 'invalid') {
      $form_state->setErrorByName('name', $this->t('Invalid name.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger->addMessage($this->t('Executed @method.', ['@method' => __METHOD__]));
  }

  /**
   * Submits only the first group.
   */
  public function submitForm1(array &$form, FormStateInterface $form_state): void {
    $this->messenger->addMessage($this->t('Executed @method.', ['@method' => __METHOD__]));
  }

}
