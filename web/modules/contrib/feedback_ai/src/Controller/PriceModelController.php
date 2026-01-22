<?php

namespace Drupal\feedback_ai\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for displaying the Price Model content.
 *
 * This controller handles the rendering of the Price Model modal.
 */
class PriceModelController extends ControllerBase {

  /**
   * Returns the Price Model content.
   *
   * This method renders the modal using the 'feedback_ai_price_modal' theme.
   *
   * @return array
   *   A render array for the Price Model modal.
   */
  public function modal() {
    return [
      '#theme' => 'feedback_ai_price_modal',
    ];
  }

}
