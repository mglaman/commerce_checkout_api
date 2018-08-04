<?php

namespace Drupal\commerce_checkout_api\Normalizer;

use Drupal\commerce_price\Price;
use Drupal\serialization\Normalizer\NormalizerBase;

class PriceNormalizer extends NormalizerBase {

  protected $supportedInterfaceOrClass = Price::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    if ($object instanceof Price) {
      $price = $object->toArray();
      $rounded_value = \Drupal::getContainer()->get('commerce_price.rounder')->round($object);
      $formatted_price = [
        '#type' => 'inline_template',
        '#template' => '{{ price|commerce_price_format }}',
        '#context' => [
          'price' => $rounded_value,
        ],
      ];
      $price['formatted'] = \Drupal::getContainer()->get('renderer')->renderPlain($formatted_price);
      return $price;
    }
    return NULL;
  }

}
