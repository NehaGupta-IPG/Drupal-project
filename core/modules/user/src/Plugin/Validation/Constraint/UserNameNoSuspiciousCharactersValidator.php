<?php

namespace Drupal\user\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NoSuspiciousCharactersValidator;

/**
 * Validates the UserNameNoSuspiciousCharacters constraint.
 */
class UserNameNoSuspiciousCharactersValidator extends NoSuspiciousCharactersValidator implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    private readonly LanguageManagerInterface $languageManager,
  ) {
    $defaultLocales = [];
    foreach ($this->languageManager->getLanguages() as $language) {
      $defaultLocales[] = $language->getId();
    }
    parent::__construct($defaultLocales);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('language_manager'));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function validate(mixed $items, Constraint $constraint): void {
    $name = $items instanceof FieldItemListInterface ? $items->first()->getString() : $items;

    parent::validate($name, $constraint);
  }

}
