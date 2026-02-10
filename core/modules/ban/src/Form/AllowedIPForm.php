<?php

declare(strict_types=1);

namespace Drupal\ban\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the Allowed IP add and edit forms.
 */
final class AllowedIPForm extends EntityForm {

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    // Parent has a protected $entityTypeManager.
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
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
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $ip = (string) $form_state->getValue('allowedIp');

    if ($this->exists($ip)) {
      $form_state->setErrorByName('allowedIp', $this->t('The IP is already on the allowed list.'));
      return;
    }
    elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
      $form_state->setErrorByName('allowedIp', $this->t('Not a valid IP.'));
      return;
    }

    // Ensure the generated machine ID is not already in use by another entity.
    $new_id = $this->canonicalIdFromIp($ip);
    $storage = $this->entityTypeManager->getStorage('ban_allowed_ip');
    $existing = $storage->load($new_id);
    /** @var \Drupal\ban\AllowedIPInterface $entity */
    $entity = $this->entity;

    if ($existing && $existing->id() !== $entity->id()) {
      $form_state->setErrorByName('allowedIp', $this->t('This IP cannot be saved because its machine ID is already in use.'));
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\ban\AllowedIPInterface $ip */
    $ip = $this->entity;
    // Use a canonical machine ID derived from the binary IP.
    $ip->set('id', $this->canonicalIdFromIp($ip->getAllowedIp()));
    $status = $ip->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('The IP %ip was added to the allowed list.', ['%ip' => $ip->getAllowedIp()]));
    }
    else {
      $this->messenger()->addMessage($this->t('The IP %ip was updated.', ['%ip' => $ip->getAllowedIp()]));
    }

    $form_state->setRedirect('entity.ban_allowed_ip.collection');
    return $status;
  }

  /**
   * Checks whether an Allowed IP configuration entity exists.
   */
  protected function exists(string $ip): bool {
    $ids = $this->entityTypeManager
      ->getStorage('ban_allowed_ip')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('allowedIp', $ip)
      ->execute();

    return (bool) $ids;
  }

  /**
   * Builds a canonical machine ID from the IP address.
   */
  private function canonicalIdFromIp(string $ip): string {
    $packed = @inet_pton($ip);
    // inet_pton() is safe here because IP was already validated.
    return $packed !== FALSE ? bin2hex($packed) : strtr($ip, ['.' => '_', ':' => '_']);
  }

}
