<?php

namespace Drupal\link\Plugin\Validation\Constraint;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Utility\Error;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\UrlValidator;

/**
 * Validates the LinkUriValid constraint.
 */
class LinkUriValidConstraintValidator extends UrlValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (isset($value)) {
      try {
        /** @var \Drupal\Core\Url $url */
        $url = $value->getUrl();
      }
      // If the URL is malformed this constraint cannot check further.
      catch (\InvalidArgumentException) {
        return;
      }
      try {
        $parts = parse_url($url->getUri());
        // Disallow external URLs using untrusted protocols.
        if ($url->isExternal() && in_array($parts['scheme'], UrlHelper::getAllowedProtocols()) && $parts['scheme'] !== 'mailto') {
          $this->context->setNode(
            $this->context->getValue(),
            $this->context->getObject(),
            $this->context->getMetadata(),
            $this->context->getPropertyPath() . '.uri'
          );
          // Create a new URL constraint for parent validation.
          $urlConstraint = new Url([
            'message' => $constraint->message,
            'requireTld' => FALSE,
            'protocols' => UrlHelper::getAllowedProtocols(),
          ]);
          parent::validate($url->getUri(), $urlConstraint);
        }
        if ($parts['scheme'] === 'mailto' && (empty($parts['path']) || !filter_var($parts['path'], FILTER_VALIDATE_EMAIL))) {
          $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $this->formatValue($url->getUri()))
            ->setCode(Url::INVALID_URL_ERROR)
            ->atPath('uri')
            ->addViolation();
        }
      }
      catch (\Exception $e) {
        $variables = Error::decodeException($e);
        \Drupal::logger('update')->error('%type: @message in %function (line %line of %file).', $variables);
      }
    }
  }

}
