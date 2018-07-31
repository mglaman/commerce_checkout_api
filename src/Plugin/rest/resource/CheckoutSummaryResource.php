<?php

namespace Drupal\commerce_checkout_api\Plugin\rest\resource;

use Drupal\Core\Cache\Cache;
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

    $response = new ResourceResponse($order);
    $response->setMaxAge(0);
    return $response;
  }

}
