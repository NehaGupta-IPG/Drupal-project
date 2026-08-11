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
   * Temporary storage of data being passed to be unserialized.
   *
   * @var array<string, mixed>|null
   */
  private ?array $unserializeData = NULL;

  /**
   * {@inheritdoc}
   */
  public function __unserialize(array $data): void {
    if ($this->overridesWakeup()) {
      // If an extending class implements its own __wakeup() method, make 
      // it gets called, otherwise PHP will ignore __wakeup() in any class
      // that has __unserialize().
      @trigger_error('Implementing __wakeup() in a subclass of ' . __CLASS__ . ' is deprecated in drupal:11.4.0 and is not supported in drupal:13.0.0. Implement __unserialize() instead. See https://www.drupal.org/node/3578423', E_USER_DEPRECATED);
      // The __wakeup method calls doUnserialize, which needs the data array, so
      // store it temporarily.
      $this->unserializeData = $data;
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
    $this->unserializeData ??= get_object_vars($this);
    $this->doUnserialize($this->unserializeData);
    $this->unserializeData = NULL;
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
   *   as passed to __unserialize().
   */
  protected function populateProperties(array $data): void {
    foreach ($data as $property => $value) {
      $normalized = $this->normalizeSerializedKey($property);
      if ($normalized === NULL) {
        continue;
      }
      $this->$normalized = $value;
    }
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
