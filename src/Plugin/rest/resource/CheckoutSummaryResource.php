<?php

namespace Drupal\commerce_checkout_api\Plugin\rest\resource;

use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @RestResource(
 *   id = "commerce_checkout_summary",
 *   label = @Translation("Checkout summary"),
 *   uri_paths = {
 *     "create" = "/api/checkout/summary"
 *   }
 * )
 */
class CheckoutSummaryResource extends CheckoutResourceBase {

  public function post(array $body, Request $request) {
    $order = $this->initiateOrder($body, $request);

    $total_summary = \Drupal::getContainer()->get('commerce_order.order_total_summary')->buildTotals($order);
    $violations = $this->checkoutOrderManager->validate($order, $this->currentUser);
    $response = new ResourceResponse([
      'violations' => $violations,
      'order' => $order,
      'adjustments' => $total_summary['adjustments'],
    ]);
    $response->setMaxAge(0);
    return $response;
  }

}
