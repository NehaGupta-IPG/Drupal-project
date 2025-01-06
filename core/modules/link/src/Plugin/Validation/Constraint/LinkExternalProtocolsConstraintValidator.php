<?php

namespace Drupal\link\Plugin\Validation\Constraint;

use Drupal\Component\Utility\UrlHelper;
use Drupal\link\LinkItemInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates the LinkExternalProtocols constraint.
 */
class LinkExternalProtocolsConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!$value instanceof LinkItemInterface) {
      throw new UnexpectedValueException($value, LinkItemInterface::class);
    }
    if ($value->isEmpty()) {
      return;
    }

    try {
      /** @var \Drupal\Core\Url $url */
      $url = $value->getUrl();
    }
    // If the URL is malformed this constraint cannot check further.
    catch (\InvalidArgumentException) {
      return;
    }

    // Disallow external URLs using untrusted protocols.
    $trusted_protocols = array_merge(UrlHelper::getAllowedProtocols(), $this->getAllowedProtocols($value));
    if ($url->isExternal() && !in_array(parse_url($url->getUri(), PHP_URL_SCHEME), $trusted_protocols)) {
      $this->context->buildViolation($constraint->message, ['@uri' => $value->uri])
        ->atPath('uri')
        ->addViolation();
    }
  }

  /**
   * Fetch the list of allowed protocols.
   *
   * @param mixed $value
   *   The value that is being validated.
   *
   * @return array
   *   The list of protocols.
   */
  protected function getAllowedProtocols(mixed $value): array {
    if (!is_null($value->getFieldDefinition()) && !empty($value->getFieldDefinition()->getSettings()['allowed_protocols'])) {
      return $value->getFieldDefinition()->getSettings()['allowed_protocols'];
    }
    return [];
  }

}
