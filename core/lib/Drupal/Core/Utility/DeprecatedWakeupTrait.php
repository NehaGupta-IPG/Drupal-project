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
      @trigger_error('Implementing __wakeup() in a subclass of ' . __CLASS__ . ' is deprecated in drupal:11.4.0 and is not supported in drupal:13.0.0. Implement __unserialize() instead. See https://www.drupal.org/node/3578423', E_USER_DEPRECATED);
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
   * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use
   *    __unserialize() instead.
   * @see https://www.drupal.org/node/3578423
   */
  public function __wakeup(): void {
    @trigger_error(__CLASS__ . '::__wakeup() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use __unserialize() instead. See https://www.drupal.org/node/3578423', E_USER_DEPRECATED);
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
   * @param array<string, mixed> $data
   *   Array of key/value pairs that represent the serialized form of the object
   *   as passed to __unserialize(), with keys already normalized by
   *   ::normalizeSerializedData().
   */
  protected function populateProperties(array $data): void {
    foreach ($data as $property => $value) {
      $this->$property = $value;
    }
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
   * @param array<mixed, mixed> $data
   *   The payload as passed to __unserialize().
   *
   * @return array<string, mixed>
   *   The payload keyed by plain property name. Keys that cannot map to a
   *   property are dropped.
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
   *   Normalized property name, or NULL when key cannot map to a property.
   */
  protected function normalizeSerializedKey(mixed $key): ?string {
    if (!is_string($key)) {
      return NULL;
    }
    // Legacy serialized payloads may contain mangled protected/private keys:
    // "\0*\0property" or "\0Class\0property".
    if ($key !== '' && $key[0] === "\0") {
      $pos = strrpos($key, "\0");
      if ($pos !== FALSE) {
        return substr($key, $pos + 1);
      }
    }
    return $key;
  }

}
