<?php

declare(strict_types = 1);

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Schema\TypeResolver;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the MatchesOtherConfigValue constraint.
 */
class MatchesOtherConfigValueConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a MatchesOtherConfigValueConstraintValidator object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly TypedConfigManagerInterface $typedConfigManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(ConfigFactoryInterface::class),
      $container->get(TypedConfigManagerInterface::class)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint):void {
    if (!$constraint instanceof MatchesOtherConfigValueConstraint) {
      throw new UnexpectedTypeException($constraint, MatchesOtherConfigValueConstraint::class);
    }

    $this_property = $this->context->getObject();
    assert($this_property instanceof TypedDataInterface);
    $this_data_definition = $this_property->getDataDefinition();

    // This validation constraint should only be used for scalar values
    // (primitives()), not for comparisons across entire arrays (sequences or
    // mappings). That is possible in theory but not in practice yet.
    // @todo Re-evaluate this after https://www.drupal.org/project/drupal/issues/3230826
    if (!is_subclass_of($this_data_definition->getClass(), PrimitiveInterface::class)) {
      throw new \LogicException(sprintf(
        'The MatchesOtherConfigValue constraint used at %s uses the %s config schema type. Only config schema types implementing %s are supported.',
        $this_property->getPropertyPath(),
        $this_data_definition->getDataType(),
        PrimitiveInterface::class,
      ));
    }

    // Determine the name of the other config object.
    $id_parts = array_map(
      fn (string $expression): mixed => TypeResolver::resolveExpression($expression, $this_property),
      (array) $constraint->id
    );
    $other_config_name = $constraint->prefix . implode('.', $id_parts);

    // When a developer uses this constraint inappropriately, guide them.
    if ($other_config_name === $this->context->getRoot()->getName()) {
      throw new \LogicException(sprintf('The MatchesOtherConfigValue constraint used in %s is configured to not look at another config object but at itself.', $this_property->getPropertyPath()));
    }
    if (!in_array($other_config_name, $this->configFactory->listAll(), TRUE)) {
      throw new \LogicException(sprintf('The config %s does not exist, and is assumed to exist by this constraint. This means a RequiredConfigDependencies is absent from the config entity type.', $other_config_name));
    }

    $other_config_object = $this->typedConfigManager->get($other_config_name);
    $other_property_to_match = $other_config_object->get($constraint->propertyPath);

    // When a developer uses this constraint inappropriately, guide them.
    $other_data_definition = $other_property_to_match->getDataDefinition();
    if ($this_data_definition->getDataType() !== $other_data_definition->getDataType()) {
      throw new \LogicException(sprintf(
        'The config schema type of this value (%s) does not match the config schema type (%s) of the %s property in the %s config object.',
        $this_data_definition->getDataType(),
        $other_data_definition->getDataType(),
        $constraint->propertyPath,
        $other_config_name,
      ));
    }
    $missing_constraints = array_diff_key($other_data_definition->getConstraints(), $this_data_definition->getConstraints());
    if (!empty($missing_constraints)) {
      throw new \LogicException(sprintf(
        'Fewer constraints are applied to this value than to the %s property in the %s config object. The following are missing: %s.',
        $constraint->propertyPath,
        $other_config_name,
        implode(', ', array_keys($missing_constraints)),
      ));
    }

    assert($value === $this_property->getValue());
    if ($value !== $other_property_to_match->getValue()) {
      $this->context->addViolation($constraint->message, [
        '@other_config_name' => $other_config_name,
        '@other_config_property_path' => $constraint->propertyPath,
        '@expected_value' => $other_property_to_match->getString(),
        '@actual_value' => $this_property->getString(),
      ]);
    }
  }

}
