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
    // Ignore telephone links because parse_url fails on three digit numbers like 911.
    if ($url->isExternal() && !in_array(parse_url($url->getUri(), PHP_URL_SCHEME), UrlHelper::getAllowedProtocols()) && !str_starts_with($url->getUri(), 'tel:')) {
      $this->context->buildViolation($constraint->message, ['@uri' => $value->uri])
        ->atPath('uri')
        ->addViolation();
    }
  }

}
