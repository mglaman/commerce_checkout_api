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

    $violations = $this->checkoutOrderManager->validate($order, $this->currentUser);
    $response = new ResourceResponse([
      'violations' => $violations,
      'order' => $order,
    ]);
    $response->setMaxAge(0);
    return $response;
  }

}
