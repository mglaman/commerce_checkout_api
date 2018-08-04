<?php

namespace Drupal\commerce_checkout_api\Normalizer;

use Drupal\commerce_order\Adjustment;
use Drupal\serialization\Normalizer\NormalizerBase;

class AdjustmentNormalizer extends NormalizerBase {

  protected $supportedInterfaceOrClass = Adjustment::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    if ($object instanceof Adjustment) {
      $adjustment =  $object->toArray();
      $adjustment['amount'] = $adjustment['amount']->toArray();
      return $adjustment;
    }
    return NULL;
  }

}
