<?php

namespace Drupal\ban\Form;

use Drupal\Core\Form\FormBase;
use Drupal\ban\BanIpManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Displays banned IP addresses.
 *
 * @internal
 */
class BanAdmin extends FormBase {

  /**
   * Constructs a new BanAdmin object.
   *
   * @param \Drupal\ban\BanIpManagerInterface $ipManager
   *   The ban IP manager.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The tempstore factory.
   */
  public function __construct(
    protected BanIpManagerInterface $ipManager,
    protected PrivateTempStoreFactory $tempStoreFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ban.ip_manager'),
      $container->get('tempstore.private'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ban_ip_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $default_ip
   *   (optional) IP address to be passed on to
   *   \Drupal::formBuilder()->getForm() for use as the default value of the IP
   *   address form field.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $default_ip = '') {
    $options = [];
    $header = [
      'address' => $this->t('Banned IP addresses'),
      'operations' => $this->t('Operations'),
    ];
    $result = $this->ipManager->findAll();
    foreach ($result as $ip) {
      $row = [];
      $row['address'] = $ip->ip;
      $links = [];
      $links['delete'] = [
        'title' => $this->t('Unblock'),
        'url' => Url::fromRoute('ban.delete', ['ban_id' => $ip->iid]),
      ];
      $row['operations'] = [
        'data' => [
          '#type' => 'operations',
          '#links' => $links,
        ],
      ];
      $options[] = $row;
    }

    $form['ip'] = [
      '#title' => $this->t('IP address'),
      '#type' => 'textfield',
      '#size' => 48,
      '#maxlength' => 40,
      '#default_value' => $default_ip,
      '#description' => $this->t('Enter a valid IP address.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#name' => 'submit_add',
    ];
    $form['ban_ip_banning_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#empty' => $this->t('No blocked IP addresses available.'),
      '#weight' => 120,
    ];
    $form['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Unblock selected'),
      '#name' => 'submit_delete',
      '#weight' => 130,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#name'] === 'submit_add') {
      $ip = trim($form_state->getValue('ip'));
      if ($this->ipManager->isBanned($ip)) {
        $form_state->setErrorByName('ip', $this->t('This IP address is already banned.'));
      }
      elseif ($ip == $this->getRequest()->getClientIP()) {
        $form_state->setErrorByName('ip', $this->t('You may not ban your own IP address.'));
      }
      elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) == FALSE) {
        $form_state->setErrorByName('ip', $this->t('Enter a valid IP address.'));
      }
    }
    elseif ($form_state->getTriggeringElement()['#name'] === 'submit_delete') {
      $tableSelectArray = $form_state->getValue('ban_ip_banning_table');
      if (reset($tableSelectArray) === 0) {
        $form_state->setErrorByName('delete', $this->t('There were no selected IP addresses to unblock.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#name'] === 'submit_add') {
      $ip = trim($form_state->getValue('ip'));
      $this->ipManager->banIp($ip);
      $this->messenger()->addStatus($this->t('The IP address %ip has been banned.', ['%ip' => $ip]));
      $form_state->setRedirect('ban.admin_page');
    }
    elseif ($form_state->getTriggeringElement()['#name'] === 'submit_delete') {
      $tableSelectArray = $form_state->getValue('ban_ip_banning_table');
      $tableSelectOptions = $form['ban_ip_banning_table']['#options'];
      $selectedIps = [];
      foreach ($tableSelectArray as $key => $value) {
        // Make sure that unchecked checkboxes (0) aren't written to the array.
        if ($value !== 0) {
          $selectedIps[] = $tableSelectOptions[$key]['address'];
        }
      }
      $this->tempStoreFactory->get('ban_ip_delete_multiple')->set('selected_ips', $selectedIps);
      $form_state->setRedirect('ban.delete.multiple');
    }
  }

}
