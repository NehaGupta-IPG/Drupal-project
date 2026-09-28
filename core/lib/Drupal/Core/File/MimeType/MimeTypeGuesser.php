<?php

namespace Drupal\Core\File\MimeType;

use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Component\Mime\MimeTypes;

/**
 * Defines a MIME type guesser that also supports stream wrapper paths.
 */
class MimeTypeGuesser implements MimeTypeGuesserInterface {

  /**
   * Constructs a MimeTypeGuesser object.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Traversable<\Symfony\Component\Mime\MimeTypeGuesserInterface> $guessers
   *   The MIME type guessers.
   */
  public function __construct(
    protected StreamWrapperManagerInterface $streamWrapperManager,
    protected \Traversable $guessers,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function guessMimeType(string $path) : ?string {
    if ($wrapper = $this->streamWrapperManager->getViaUri($path)) {
      // Get the real path from the stream wrapper, if available. Files stored
      // in remote file systems will not have one.
      $real_path = $wrapper->realpath();
      if ($real_path !== FALSE) {
        $path = $real_path;
      }
    }

    foreach ($this->guessers as $guesser) {
      if ($guesser->isGuesserSupported()) {
        $mime_type = $guesser->guessMimeType($path);
        if ($mime_type !== NULL) {
          return $mime_type;
        }
      }
    }

    return 'application/octet-stream';
  }

  /**
   * {@inheritdoc}
   */
  public function isGuesserSupported(): bool {
    return TRUE;
  }

  /**
   * A helper function to register with Symfony's singleton MIME type guesser.
   *
   * Symfony's default mimetype guessers have dependencies on PHP's fileinfo
   * extension or being able to run the system command file. Drupal's guesser
   * does not have these dependencies.
   *
   * The registration holds only a weak reference to Drupal's guesser. After
   * the container that owns the guesser is garbage collected, Symfony falls
   * back to its default guessers until this method is called again.
   *
   * @see \Symfony\Component\Mime\MimeTypes
   */
  public static function registerWithSymfonyGuesser(
    ContainerInterface $container,
  ): void {
    $guesser = new MimeTypes();
    // Symfony's static MIME registry outlives Drupal's container. Reference
    // Drupal's guesser weakly so the registry does not keep a discarded
    // container alive.
    $guesser->registerGuesser(new class($container->get('file.mime_type.guesser')) implements MimeTypeGuesserInterface {

      /**
       * The guesser owned by Drupal's container.
       *
       * @var \WeakReference<\Symfony\Component\Mime\MimeTypeGuesserInterface>
       */
      private readonly \WeakReference $guesser;

      public function __construct(MimeTypeGuesserInterface $guesser) {
        $this->guesser = \WeakReference::create($guesser);
      }

      /**
       * {@inheritdoc}
       */
      public function isGuesserSupported(): bool {
        return $this->guesser->get()?->isGuesserSupported() ?? FALSE;
      }

      /**
       * {@inheritdoc}
       */
      public function guessMimeType(string $path): ?string {
        return $this->guesser->get()?->guessMimeType($path);
      }

    });
    MimeTypes::setDefault($guesser);
  }

}
