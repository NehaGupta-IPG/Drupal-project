<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\CallableResolver;
use Symfony\Component\Mime\Email;

/**
 * A service which renders emails according to an email definition.
 */
class EmailRenderer implements EmailRendererInterface {

  public function __construct(
    protected readonly CallableResolver $callableResolver,
    protected readonly RendererInterface $renderer,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function render(EmailPluginInterface $plugin, array $params, string $langcode): Email {
    // Create params object and let modules alter it.
    $params = new EmailParams(
      $plugin->getPluginDefinition(),
      $params,
      ['langcode' => $langcode],
    );
    $this->moduleHandler->alter('email_params', $params);

    // @todo Replace with execution environment once that is available.
    // @see: https://www.drupal.org/i/3536307
    return $this->renderer->executeInRenderContext(new RenderContext(), function () use ($plugin, $params) {
      // @todo Allow contrib and custom code to provide a custom Email class.
      $email = new Email();
      // Build render array.
      $build = $plugin->build($email, $params);

      // Render the result.
      if (is_array($build)) {
        // Let modules alter the render array.
        $context = [...$params->getArguments(), 'email' => $email];
        $this->moduleHandler->alter('email_build', $build, $context);

        $build['#theme'] = 'email';
        $build['#props'] = $context;
        $email->html((string) $this->renderer->render($build));

        // Make render metadata available to email_alter (contains #attachments
        // and #cache).
        $metadata = BubbleableMetadata::createFromRenderArray($build);
      }
      else {
        $metadata = new BubbleableMetadata();
      }

      // Let modules alter the resulting email.
      $context = [...$params->getArguments(), 'metadata' => $metadata];
      $this->moduleHandler->alter('email', $email, $context);

      return $email;
    });
  }

}
