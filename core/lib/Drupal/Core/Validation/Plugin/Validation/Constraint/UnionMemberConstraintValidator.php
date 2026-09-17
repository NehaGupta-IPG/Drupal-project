<?php

declare(strict_types=1);

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\Plugin\DataType\Union;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\UnionDataDefinitionInterface;
use Drupal\Core\TypedData\Validation\TypedDataAwareValidatorTrait;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the UnionMember constraint.
 *
 * @see \Drupal\Core\Validation\Plugin\Validation\Constraint\UnionMemberConstraint
 */
class UnionMemberConstraintValidator extends ConstraintValidator {

  use TypedDataAwareValidatorTrait;

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!$constraint instanceof UnionMemberConstraint) {
      throw new UnexpectedTypeException($constraint, UnionMemberConstraint::class);
    }
    $data = $this->getTypedData();
    if (!$data instanceof Union) {
      throw new UnexpectedTypeException($data, Union::class);
    }
    $definition = $data->getDataDefinition();
    if (!$definition instanceof UnionDataDefinitionInterface) {
      throw new UnexpectedTypeException($definition, UnionDataDefinitionInterface::class);
    }

    $raw_value = $data->getValue();
    // A NULL value is not a member mismatch. Whether NULL is allowed at all is
    // decided by the NotNull constraint of the union definition.
    if ($raw_value === NULL) {
      return;
    }

    $members = $definition->getMemberDefinitions();
    $resolved = $data->getResolvedDefinition();

    if ($resolved === NULL) {
      $this->context->addViolation($constraint->message);
      // Report the violations of the member that came closest, so that the
      // message above is not the only thing a caller has to go on. Ties are
      // broken by declaration order.
      $best = NULL;
      $best_count = NULL;
      foreach ($members as $member) {
        $candidate = $this->buildMember($data, $member, $raw_value);
        if ($candidate === NULL) {
          continue;
        }
        $count = count($candidate->validate());
        if ($best_count === NULL || $count < $best_count) {
          $best_count = $count;
          $best = $candidate;
        }
      }
      if ($best !== NULL) {
        $this->addMemberViolations($best);
      }
      return;
    }

    // The value took a member, so report whatever that member itself objects
    // to. The single structural match of Union does not validate, so this is
    // the point at which a value of the right shape but of the wrong content
    // is reported.
    $delegate = $this->buildMember($data, $resolved, $raw_value);
    if ($delegate !== NULL) {
      $this->addMemberViolations($delegate);
    }

    if (!$constraint->strict) {
      return;
    }
    // In strict mode an overlap between the members is a violation of its own:
    // the union is meant to describe alternatives that a value cannot take at
    // the same time.
    $matching = [];
    foreach ($members as $member) {
      $candidate = $this->buildMember($data, $member, $raw_value);
      if ($candidate !== NULL && count($candidate->validate()) === 0) {
        $matching[] = $this->memberLabel($member);
      }
    }
    if (count($matching) > 1) {
      $this->context->addViolation($constraint->ambiguousMessage, ['@types' => implode(', ', $matching)]);
    }
  }

  /**
   * Builds a detached typed data object for a member.
   *
   * The object is built without a name and without a parent, so that adding
   * its violations to the context does not append the name of the union to the
   * property path a second time.
   *
   * @param \Drupal\Core\TypedData\Plugin\DataType\Union $union
   *   The union the member belongs to, used to reach the typed data manager.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $member
   *   The member definition to build.
   * @param mixed $value
   *   The value to set on the member.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface|null
   *   The typed data object, or NULL if the member cannot hold the value at
   *   all, in which case it simply does not match.
   */
  protected function buildMember(Union $union, DataDefinitionInterface $member, $value): ?TypedDataInterface {
    try {
      return $union->getTypedDataManager()->create($member, $value, '', NULL);
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Adds the violations of a member to the context of the union.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $member_data
   *   The typed data object of the member to validate.
   */
  protected function addMemberViolations(TypedDataInterface $member_data): void {
    $this->context->getValidator()
      ->inContext($this->context)
      // Pass along FALSE as $root_call, as the member is validated as part of
      // the typed data tree the union sits in.
      ->validate($member_data, NULL, NULL, FALSE);
  }

  /**
   * Returns a human readable name for a member.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $member
   *   The member definition to name.
   *
   * @return string
   *   The label of the member, or its data type if it has no label.
   */
  protected function memberLabel(DataDefinitionInterface $member): string {
    $label = $member->getLabel();
    return $label === NULL ? $member->getDataType() : (string) $label;
  }

}
