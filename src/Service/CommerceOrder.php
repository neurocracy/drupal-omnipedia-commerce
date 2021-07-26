<?php

namespace Drupal\omnipedia_commerce\Service;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\omnipedia_commerce\Service\CommerceOrderInterface;

/**
 * The Omnipedia Commerce order helper service.
 */
class CommerceOrder implements CommerceOrderInterface {

  /**
   * {@inheritdoc}
   */
  public function getProductsFromOrder(OrderInterface $order): array {

    /** @var \Drupal\commerce_product\Entity\ProductInterface[] */
    $products = [];

    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $orderItem */
    foreach ($order->getItems() as $orderItem) {

      /** @var \Drupal\commerce\PurchasableEntityInterface|null The product variation entity or null. */
      $productVariation = $orderItem->getPurchasedEntity();

      // Skip to the next order item if the product variation is not an object,
      // i.e. null.
      if (!\is_object($productVariation)) {
        continue;
      }

      /** @var \Drupal\commerce_product\Entity\ProductInterface|null The purchased product or null. */
      $product = $productVariation->getProduct();

      // Skip to the next order item if we didn't get a product entity.
      if (!\is_object($product)) {
        continue;
      }

      $products[] = $product;

    }

    return $products;

  }

}
