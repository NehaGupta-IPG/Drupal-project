<?php

namespace Drupal\ban\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the Allowed IP add and edit forms.
 */
class AllowedIPForm extends EntityForm {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\ban\AllowedIPInterface $ip */
    $ip = $this->entity;

    $form['allowedIp'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed IP'),
      '#default_value' => $ip->getAllowedIp(),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $ip = $form_state->getValue('allowedIp');
    if ($this->exists($ip)) {
      $form_state->setErrorByName('allowedIp', $this->t('The IP is already on the allowed list.'));
    }
    elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
      $form_state->setErrorByName('allowedIp', $this->t('Not a valid IP.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\ban\AllowedIPInterface $ip */
    $ip = $this->entity;
    // Use the IP value as the entity ID to ensure uniqueness.
    $ip->set('id', $ip->getAllowedIp());
    $status = $ip->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('The IP %ip was added to the allowed list.', ['%ip' => $ip->getAllowedIp()]));
    }
    else {
      $this->messenger()->addMessage($this->t('The IP %ip was updated.', ['%ip' => $ip->getAllowedIp()]));
    }

    $form_state->setRedirect('entity.ban_allowed_ip.collection');
  }

  /**
   * Checks whether an Allowed IP configuration entity exists.
   */
  protected function exists(string $ip): bool {
    $ids = $this->entityTypeManager->getStorage('ban_allowed_ip')->getQuery()
      ->condition('allowedIp', $ip)
      ->execute();
    return (bool) $ids;
  }

}
