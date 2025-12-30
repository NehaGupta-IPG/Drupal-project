<?php

namespace Drupal\views\Plugin\views\argument_default;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsArgumentDefault;
use Drupal\views\Plugin\views\argument\Date as DateArgument;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The current date argument default handler.
 */
#[ViewsArgumentDefault(
  id: 'date',
  title: new TranslatableMarkup('Current date'),
)]
class Date extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected DateFormatterInterface $dateFormatter, protected TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument(): string {

    // The Date argument handlers provide their own format strings, otherwise
    // use a default.
    $format = $this->argument instanceof DateArgument ? $this->argument->getArgFormat() : 'Y-m-d';

    $request_time = $this->time->getRequestTime();

    return $this->dateFormatter->format($request_time, 'custom', $format);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
