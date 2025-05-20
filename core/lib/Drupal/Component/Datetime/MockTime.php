<?php

namespace Drupal\Component\Datetime;

/**
 * Provides a mock class for obtaining system time in tests.
 */
class MockTime implements TimeInterface {

  /**
   * Constructs a MockTime object.
   *
   * @param float $requestTime
   *   The request time that should be returned.
   * @param float $currentTime
   *   The current time that should be returned.
   */
  public function __construct(protected float $requestTime = 0, protected float $currentTime = 0) {}

  /**
   * {@inheritdoc}
   */
  public function getRequestTime() {
    return (int) $this->requestTime;
  }

  /**
   * Set the request time as float.
   *
   * @param float $requestTime
   *   The request time that should be returned.
   */
  public function setRequestTime(float $requestTime): void {
    $this->requestTime = $requestTime;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestMicroTime() {
    return $this->requestTime;

  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentTime() {
    return (int) $this->currentTime;
  }

  /**
   * Set the current time as float.
   *
   * @param float $currentTime
   *   The request time that should be returned.
   */
  public function setCurrentTime(float $currentTime): void {
    $this->currentTime = $currentTime;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentMicroTime() {
    return $this->currentTime;
  }

}
