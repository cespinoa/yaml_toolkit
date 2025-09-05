<?php

namespace Drupal\yaml_toolkit\Helper;

use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\yaml_toolkit\Contract\Helper\StreamWrapperManagerHelperInterface;

/**
 * Helper to avoid direct use of streamWrapperManager.
 *
 * StreamWrapperManager is not compatible with unit tests.
 */
class StreamWrapperManagerHelper implements StreamWrapperManagerHelperInterface {

  /**
   * The StreamWrapperManagerInterface service.
   */
  protected StreamWrapperManagerInterface $streamWrapperManager;

  /**
   * Constructor.
   */
  public function __construct(
    StreamWrapperManagerInterface $streamWrapperManager,
  ) {
    $this->streamWrapperManager = $streamWrapperManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getScheme(string $filePath): bool {
    return $this->streamWrapperManager->getScheme($filePath);
  }

}
