<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\File\MimeType;

use Drupal\Core\File\MimeType\MimeTypeGuesser;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Component\Mime\MimeTypes;

/**
 * Tests that Symfony's MIME registry does not retain a discarded container.
 */
#[CoversClass(MimeTypeGuesser::class)]
#[Group('File')]
class MimeTypeGuesserLifetimeTest extends UnitTestCase {

  /**
   * Tests delegation, container garbage collection, and re-registration.
   */
  public function testContainerLifetime(): void {
    $original = MimeTypes::getDefault();
    try {
      // Distinct results verify that each registration uses the new container.
      foreach (['first', 'second'] as $type) {
        $container = new ContainerBuilder();
        // Model a guesser that references its owning container, as Drupal's
        // guesser does through its tagged iterator.
        $service = new class($container, $type) implements MimeTypeGuesserInterface {

          public function __construct(private object $container, private string $type) {}

          public function isGuesserSupported(): bool {
            return TRUE;
          }

          public function guessMimeType(string $path): ?string {
            return $this->type;
          }

        };
        $container->set('file.mime_type.guesser', $service);
        $reference = \WeakReference::create($container);
        MimeTypeGuesser::registerWithSymfonyGuesser($container);
        // The live Drupal guesser takes precedence over Symfony's guessers.
        $this->assertSame($type, MimeTypes::getDefault()->guessMimeType('private://example.txt'));

        // The static registry must not keep the container-service cycle alive.
        unset($container, $service);
        gc_collect_cycles();
        $this->assertNull($reference->get());

        // After collection, Symfony skips the adapter and uses its own guessers.
        $this->assertNotNull(MimeTypes::getDefault()->guessMimeType(__FILE__));
      }
    }
    finally {
      MimeTypes::setDefault($original);
    }
  }

}
