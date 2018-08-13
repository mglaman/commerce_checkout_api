<?php

namespace Drupal\commerce_checkout_api\Plugin\rest\resource;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_payment\Entity\PaymentMethod;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @RestResource(
 *   id = "commerce_checkout_finalize",
 *   label = @Translation("Checkout finalize"),
 *   uri_paths = {
 *     "create" = "/api/checkout"
 *   }
 * )
 */
class CheckoutFinalizeResource extends CheckoutResourceBase {

  public function post(array $body, Request $request) {
    // Do an initial validation of the payload before any processing.
    if (empty($body['payment'])) {
      throw new UnprocessableEntityHttpException('You must provide payment data.');
    }
    if (empty($body['payment']['gateway'])) {
      throw new UnprocessableEntityHttpException('You must provide a payment gateway.');
    }
    $gateway = PaymentGateway::load($body['payment']['gateway']);
    if (!$gateway instanceof PaymentGateway) {
      throw new UnprocessableEntityHttpException('You must provide a payment gateway.');
    }
    if (empty($body['payment']['nonce'])) {
      throw new UnprocessableEntityHttpException('You must provide a payment nonce.');
    }

    $order = $this->initiateOrder($body, $request);

    // References are all crazy now.
    $billing_profile = $order->getBillingProfile();
    $billing_profile->save();
    $order->setBillingProfile($billing_profile);

    foreach ($order->getItems() as $item) {
      $item->enforceIsNew(FALSE);
      $item->save();
    };
    $order->save();

    // This is opinionated to Braintree.
    /** @var \Drupal\commerce_braintree\Plugin\Commerce\PaymentGateway\HostedFields $gateway_plugin */
    $gateway_plugin = $gateway->getPlugin();
    $body['payment']['payment_method_nonce'] = $body['payment']['nonce'];
    $body['payment']['last2'] = $body['payment']['details']['lastTwo'];
    $body['payment']['card_type'] = $body['payment']['details']['cardType'];

    $payment_method = PaymentMethod::create([
      'type' => 'credit_card',
      'payment_gateway' => $gateway->id(),
      'uid' => $order->getCustomerId(),
      'billing_profile' => $order->getBillingProfile(),
    ]);
    $payment_method->setReusable(FALSE);
    $gateway_plugin->createPaymentMethod($payment_method, $body['payment']);
    $payment_method->save();

    $order->set('payment_gateway', $gateway);
    $order->set('payment_method', $payment_method);

    $payment = Payment::create([
      'state' => 'new',
      'amount' => $order->getTotalPrice(),
      'payment_gateway' => $gateway->id(),
      'order_id' => $order->id(),
      'payment_method' => $payment_method,
    ]);

    $gateway_plugin->createPayment($payment, TRUE);
    $payment->save();

    // It refreshes even though we had refreshed earlier.
    $order->getState()->applyTransition(
      $order->getState()->getTransitions()['place']
    );
    $order->save();

    $debug = $order->toArray();

    $total_summary = \Drupal::getContainer()->get('commerce_order.order_total_summary')->buildTotals($order);
    $response = new ResourceResponse([
      'order' => $order,
      'adjustments' => $total_summary['adjustments'],
    ]);
    $response->setMaxAge(0);
    return $response;
  }

}
