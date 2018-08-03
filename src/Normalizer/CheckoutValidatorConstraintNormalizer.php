<?php

namespace Drupal\commerce_checkout_api\Normalizer;

use Drupal\commerce_checkout\CheckoutValidator\CheckoutValidatorConstraint;
use Drupal\serialization\Normalizer\NormalizerBase;

class CheckoutValidatorConstraintNormalizer extends NormalizerBase {

  protected $supportedInterfaceOrClass = CheckoutValidatorConstraint::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    if ($object instanceof CheckoutValidatorConstraint) {
      return ['message' => $object->getMessage()];
    }
    return NULL;
  }

}
