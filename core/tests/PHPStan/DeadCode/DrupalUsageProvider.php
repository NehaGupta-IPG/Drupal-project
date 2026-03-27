<?php

declare(strict_types=1);

namespace Drupal\PHPStan\DeadCode;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use ShipMonk\PHPStan\DeadCode\Enum\AccessType;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMethodRef;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMethodUsage;
use ShipMonk\PHPStan\DeadCode\Graph\ClassPropertyRef;
use ShipMonk\PHPStan\DeadCode\Graph\ClassPropertyUsage;
use ShipMonk\PHPStan\DeadCode\Graph\UsageOrigin;
use ShipMonk\PHPStan\DeadCode\Provider\MemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;

/**
 * Marks Drupal code as used in shipmonk/dead-code-detector.
 */
final class DrupalUsageProvider implements MemberUsageProvider {

  /**
   * {@inheritdoc}
   */
  public function getUsages(Node $node, Scope $scope): array {
    if (!$node instanceof InClassNode) { // @phpstan-ignore phpstanApi.instanceofAssumption
      return [];
    }

    $reflection = $node->getClassReflection()->getNativeReflection();
    $className = $reflection->getName();

    $usages = [];

    // PHP attribute classes.
    if ($reflection->getAttributes(\Attribute::class) !== []) {
      foreach ($reflection->getProperties() as $property) {
        if ($property->getDeclaringClass()->getName() !== $className) {
          continue;
        }
        $origin = UsageOrigin::createVirtual(
          $this,
          VirtualUsageData::withNote('Attribute property read via get_object_vars() in AttributeBase::get()'),
        );
        $ref = new ClassPropertyRef($className, $property->getName(), FALSE);
        $usages[] = new ClassPropertyUsage($origin, $ref, AccessType::READ);
        $usages[] = new ClassPropertyUsage($origin, $ref, AccessType::WRITE);
      }
    }

    foreach ($reflection->getMethods() as $method) {
      if ($method->getDeclaringClass()->getName() !== $className) {
        continue;
      }

      // #[Hook] methods.
      if ($method->getAttributes('Drupal\Core\Hook\Attribute\Hook') !== []) {
        $usages[] = new ClassMethodUsage(
          UsageOrigin::createVirtual($this, VirtualUsageData::withNote('Hook method invoked by Drupal hook system')),
          new ClassMethodRef($className, $method->getName(), FALSE),
        );
      }

      // Preprocess methods.
      if ($method->isPublic() && str_starts_with($method->getName(), 'preprocess')) {
        $usages[] = new ClassMethodUsage(
          UsageOrigin::createVirtual($this, VirtualUsageData::withNote('Preprocess method invoked by Drupal theme system')),
          new ClassMethodRef($className, $method->getName(), FALSE),
        );
      }
    }

    return $usages;
  }

}
