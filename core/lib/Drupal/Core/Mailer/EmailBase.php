<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\ArgumentsResolver;
use Drupal\Core\Mailer\Attribute\EmailBody;
use Drupal\Core\Mailer\Attribute\EmailSubject;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Render\RenderableInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Mime\Email;

/**
 * Base class for email plugins.
 */
class EmailBase extends PluginBase implements EmailPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function build(Email $email, EmailParams $params): array|RenderableInterface|null {
    $resolver = $this->getArgumentResolver($email, $params);

    $subjectMethod = $this->getSubjectMethod($params);
    $subjectArgs = $resolver->getArguments($subjectMethod);
    $subject = $subjectMethod(...$subjectArgs);
    if ($subject) {
      $email->subject((string) PlainTextOutput::renderFromHtml($subject));
    }

    $bodyMethod = $this->getBodyMethod($params);
    $bodyArguments = $resolver->getArguments($bodyMethod);
    return $bodyMethod(...$bodyArguments);
  }

  /**
   * Returns the method used to build the subject.
   *
   * @param \Drupal\Core\Mailer\EmailParams $params
   *   The email plugin parameters.
   *
   * @return \Closure(): string|\Stringable|null
   *   The subject method.
   */
  protected function getSubjectMethod(EmailParams $params): \Closure {
    $reflectionClass = new \ReflectionClass($this);
    while ($reflectionClass) {
      foreach ($reflectionClass->getMethods() as $method) {
        // Only process a method if it is declared on the current class.
        // Methods on the parent class will be processed later. This allows
        // for a parent to have an attribute and an overriding class does
        // not need one.
        if (
          $method->getDeclaringClass()->getName() === $reflectionClass->getName() &&
          $method->getAttributes(EmailSubject::class) !== []
        ) {
          return $this->{$method->getName()}(...);
        }
      }
      $reflectionClass = $reflectionClass->getParentClass();
    }

    return match(TRUE) {
      $params->hasAttribute('subject') => (fn (TranslatableMarkup $subject) => $subject),
      default => (fn () => NULL),
    };
  }

  /**
   * Returns the method used to build the body.
   *
   * @param \Drupal\Core\Mailer\EmailParams $params
   *   The email plugin parameters.
   *
   * @return \Closure(): array|RenderableInterface|null
   *   The body method.
   */
  protected function getBodyMethod(EmailParams $params): \Closure {
    $reflectionClass = new \ReflectionClass($this);
    while ($reflectionClass) {
      foreach ($reflectionClass->getMethods() as $method) {
        // Only process a method if it is declared on the current class.
        // Methods on the parent class will be processed later. This allows
        // for a parent to have an attribute and an overriding class does
        // not need one.
        if (
          $method->getDeclaringClass()->getName() === $reflectionClass->getName() &&
          $method->getAttributes(EmailBody::class) !== []
        ) {
          return $this->{$method->getName()}(...);
        }
      }
      $reflectionClass = $reflectionClass->getParentClass();
    }

    return (fn() => []);
  }

  /**
   * Returns an argument resolver instance suitable for the given params.
   *
   * @param \Symfony\Component\Mime\Email $email
   *   The email instance.
   * @param \Drupal\Core\Mailer\EmailParams $params
   *   The email plugin parameters.
   */
  protected function getArgumentResolver(Email $email, EmailParams $params): ArgumentsResolver {
    $scalars = [];
    $objects = [
      'email' => $email,
    ];
    $wildcards = [];

    foreach ($params->getArguments() as $key => $value) {
      if (is_object($value)) {
        $objects[$key] = $value;
      }
      else {
        $scalars[$key] = $value;
      }
    }

    return new ArgumentsResolver($scalars, $objects, $wildcards);
  }

}
