<?php

namespace Drupal\commerce_checkout_api\CheckoutValidator;

use Drupal\commerce_checkout\CheckoutValidator\CheckoutValidatorConstraint;
use Drupal\commerce_checkout\CheckoutValidator\CheckoutValidatorConstraintList;
use Drupal\commerce_checkout\CheckoutValidator\CheckoutValidatorInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Session\AccountInterface;

class BillingInformationCheckoutValidator implements CheckoutValidatorInterface {

  /**
   * {@inheritdoc}
   */
  public function validate(OrderInterface $order, AccountInterface $account, $phase = self::PHASE_ENTER) {
    $list = new CheckoutValidatorConstraintList();

    if (!$order->getBillingProfile()) {
      $list->add(new CheckoutValidatorConstraint(t('The order must billing information.')));
    }

    return $list;
  }

}
