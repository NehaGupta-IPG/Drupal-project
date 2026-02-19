<?php

namespace Drupal\node\ParamConverter;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\Routing\Route;

/**
 * Provides upcasting for a node entity in preview.
 */
class NodePreviewConverter implements ParamConverterInterface {

  /**
   * Stores the tempstore factory.
   *
   * @var PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Constructs a new NodePreviewConverter.
   *
   * @param PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory) {
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $store = $this->tempStoreFactory->get('node_preview');
    if ($form_state = $store->get($value)) {
      return $form_state->getFormObject()->getEntity();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    if (!empty($definition['type']) && $definition['type'] == 'node_preview') {
      return TRUE;
    }
    return FALSE;
  }

}
