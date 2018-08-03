<?php

namespace Drupal\commerce_checkout_api\CheckoutValidator;

use Drupal\commerce_checkout\CheckoutValidator\CheckoutValidatorConstraint;
use Drupal\commerce_checkout\CheckoutValidator\CheckoutValidatorConstraintList;
use Drupal\commerce_checkout\CheckoutValidator\CheckoutValidatorInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\Core\Session\AccountInterface;

class ShippingInformationCheckoutValidator implements CheckoutValidatorInterface {

  /**
   * {@inheritdoc}
   */
  public function validate(OrderInterface $order, AccountInterface $account, $phase = self::PHASE_ENTER) {
    $list = new CheckoutValidatorConstraintList();

    // @todo shipping will not work.
    return $list;

    $order_type = OrderType::load($order->bundle());
    $shipping_settings = $order_type->getThirdPartySetting('commerce_shipping', 'shipment_type', NULL);
    if (!$shipping_settings) {
      return $list;
    }

    if ($order->get('shipments')->isEmpty()) {
      $list->add(new CheckoutValidatorConstraint('Shipping not calculated'));
    }
    else {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
      foreach ($order->get('shipments')->referencedEntities() as $shipment) {
        if (empty($shipment->getShippingProfile())) {
          $list->add(new CheckoutValidatorConstraint('Shipping information not provided'));
        }
      }
    }

    return $list;
  }

}
