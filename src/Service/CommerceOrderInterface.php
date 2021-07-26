<?php

namespace Drupal\omnipedia_commerce\Service;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * The Omnipedia Commerce order helper service interface.
 */
interface CommerceOrderInterface {

  /**
   * Get all products in an order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A Commerce Order containing products.
   *
   * @return \Drupal\commerce_product\Entity\ProductInterface[]
   *   Zero or more Commerce Product entities found in the order entity.
   */
  public function getProductsFromOrder(OrderInterface $order): array;

}
