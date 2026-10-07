<?php

declare(strict_types=1);

namespace Drupal\Core\Utility;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Trait that provides backwards compatibility for __wakeup().
 *
 * This trait provides backwards compatibility for two separate use cases:
 * - Serializable core classes implement __unserialize(). As a result,
 *   __wakeup() methods in contrib or custom classes that extend any of those
 *   classes will not run during unserialization. Core classes can use this
 *   trait to make sure a subclass's __wakeup() method is invoked during
 *   unserialization.
 * - Direct calls to __wakeup(). This trait provides __wakeup() for classes that
 *   have had it replaced by __unserialize(), in case there are calls from
 *   subclasses or external code.
 *
 * @internal
 */
trait DeprecatedWakeupTrait {

  /**
   * {@inheritdoc}
   */
  public function __unserialize(array $data): void {
    // PHP hands every payload to __unserialize() once the class declares it,
    // including payloads written by a legacy __sleep() implementation. Those
    // carry mangled keys for non-public properties, so normalize them here,
    // before any doUnserialize() implementation looks up keys such as
    // '_serviceIds' by name.
    $data = $this->normalizeSerializedData($data);
    if ($this->overridesWakeup()) {
      // If an extending class implements its own __wakeup() method, make sure
      // it gets called, otherwise PHP will ignore __wakeup() in any class that
      // has __unserialize().
      @trigger_error('Implementing __wakeup() in a subclass of ' . __CLASS__ . ' is deprecated in drupal:11.5.0 and is not supported in drupal:13.0.0. Implement __unserialize() instead. See https://www.drupal.org/node/3578423', E_USER_DEPRECATED);
      // Emulate PHP's legacy behavior: the properties are populated from the
      // payload first, and only then is __wakeup() invoked. This way a
      // __wakeup() implementation that does not call parent::__wakeup() still
      // ends up with a fully populated object, exactly as it did before
      // __unserialize() was introduced.
      $this->populateProperties($data);
      // @phpstan-ignore method.deprecated
      $this->__wakeup();
      return;
    }

    $this->doUnserialize($data);
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use
   *    __unserialize() instead.
   * @see https://www.drupal.org/node/3578423
   */
  public function __wakeup(): void {
    @trigger_error(__CLASS__ . '::__wakeup() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use __unserialize() instead. See https://www.drupal.org/node/3578423', E_USER_DEPRECATED);
    // By the time __wakeup() runs, the object's properties hold the serialized
    // values: either PHP populated them (legacy payload, no __unserialize()) or
    // __unserialize() did before invoking this method. Feed them to
    // doUnserialize() so that, for example, services are restored from the
    // container.
    $this->doUnserialize(get_object_vars($this));
  }

  /**
   * Helper that repopulates class properties from container on unserialize.
   *
   * @param array<string, mixed> $data
   *   Key-value map as expected to be returned from __serialize().
   */
  abstract protected function doUnserialize(array $data): void;

  /**
   * Checks whether a class using this trait overrides __wakeup().
   *
   * @return bool
   *   TRUE if the class overrides __wakeup(), otherwise FALSE.
   */
  protected function overridesWakeup(): bool {
    $reflection_trait = new \ReflectionClass(DependencySerializationTrait::class);
    $reflection_class = new \ReflectionClass($this);
    try {
      $reflection_trait_method = $reflection_trait->getMethod('__wakeup');
      $reflection_class_method = $reflection_class->getMethod('__wakeup');
      return $reflection_class_method->getFileName() !== $reflection_trait_method->getFileName();
    }
    catch (\ReflectionException) {
      // Do nothing on invalid methods.
    }
    return FALSE;
  }

  /**
   * Populate class properties from values passed in during unserialization.
   *
   * Properties that already hold the given value are left untouched. This
   * happens when a legacy __wakeup() implementation chains to the trait's
   * __wakeup() after __unserialize() has populated the object: assigning a
   * readonly property a second time would throw, even with the same value.
   *
   * @param array<string, mixed> $data
   *   Array of key/value pairs that represent the serialized form of the object
   *   as passed to __unserialize(), with keys already normalized by
   *   ::normalizeSerializedData().
   */
  protected function populateProperties(array $data): void {
    $current = get_object_vars($this);
    foreach ($data as $property => $value) {
      if (array_key_exists($property, $current) && $current[$property] === $value) {
        continue;
      }
      if (is_string($property) && $property !== '' && $property[0] === "\0") {
        $this->populatePrivateProperty($property, $value);
        continue;
      }
      $this->$property = $value;
    }
  }

  /**
   * Populates a private property declared by another class in the hierarchy.
   *
   * A property that is private to a subclass cannot be assigned from the scope
   * of this class: PHP either throws or, when the subclass implements __set(),
   * silently reroutes the value there. Assign it in the scope of the declaring
   * class instead, as a native unserialize() effectively does.
   *
   * @param string $key
   *   The mangled property key, "\0Class\0property".
   * @param mixed $value
   *   The value to assign.
   */
  private function populatePrivateProperty(string $key, mixed $value): void {
    $parts = explode("\0", $key, 3);
    if (count($parts) !== 3 || !$this instanceof $parts[1]) {
      // Not a private property of a class in the hierarchy, for example the
      // declaring class was renamed. Assign it as a plain property, which is
      // what happened with the plain property name before.
      $this->{end($parts)} = $value;
      return;
    }
    [, $class, $property] = $parts;
    \Closure::bind(function (string $property, mixed $value): void {
      // As in ::populateProperties(), leave a property that already holds the
      // given value untouched, so that a readonly property is not assigned
      // twice.
      $current = get_object_vars($this);
      if (!array_key_exists($property, $current) || $current[$property] !== $value) {
        $this->$property = $value;
      }
    }, $this, $class)($property, $value);
  }

  /**
   * Normalizes the keys of a serialized payload for backward compatibility.
   *
   * Payloads written by a legacy __sleep() implementation contain mangled keys
   * for protected and private properties ("\0*\0property" or
   * "\0Class\0property"). Payloads written by __serialize() contain plain
   * property names. This method maps both forms to plain property names so
   * that doUnserialize() implementations can rely on a single key format.
   *
   * The one exception is a private property of another class in the
   * hierarchy, typically a subclass with its own legacy __sleep(). Such a
   * property cannot be assigned from the scope of this class, so its key stays
   * mangled and ::populateProperties() assigns it in the scope of the
   * declaring class.
   *
   * @param array<mixed, mixed> $data
   *   The payload as passed to __unserialize().
   *
   * @return array<string, mixed>
   *   The payload keyed by property name. Keys that cannot map to a property
   *   are dropped.
   */
  protected function normalizeSerializedData(array $data): array {
    $normalized = [];
    foreach ($data as $key => $value) {
      $property = $this->normalizeSerializedKey($key);
      if ($property === NULL) {
        continue;
      }
      $normalized[$property] = $value;
    }
    return $normalized;
  }

  /**
   * Normalizes serialized property keys for backward compatibility.
   *
   * @param mixed $key
   *   Serialized key.
   *
   * @return string|null
   *   Normalized property name, or NULL when key cannot map to a property. The
   *   key of a private property of another class in the hierarchy is returned
   *   unchanged.
   */
  protected function normalizeSerializedKey(mixed $key): ?string {
    if (!is_string($key)) {
      return NULL;
    }
    // Legacy serialized payloads may contain mangled protected/private keys:
    // "\0*\0property" or "\0Class\0property".
    if ($key !== '' && $key[0] === "\0") {
      $parts = explode("\0", $key, 3);
      if (count($parts) === 3 && $parts[1] !== '*' && strcasecmp($parts[1], self::class) !== 0) {
        // Private to another class: keep the declaring class in the key.
        return $key;
      }
      return end($parts);
    }
    return $key;
  }

}
