<?php

namespace Drupal\omnipedia_commerce\Service;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\omnipedia_core\Entity\NodeInterface;

/**
 * The Omnipedia content access product service interface.
 */
interface ContentAccessProductInterface {

  /**
   * Set the base content access product ID.
   *
   * @param string|null $productId
   *   The product entity ID to set. Note that this entity must already exist in
   *   storage (i.e. is saved) or it'll be ignored. If this is null, the base
   *   product ID will be unset.
   */
  public function setBaseProductId(?string $productId): void;

  /**
   * Set the base content access product.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product entity to set. Note that this entity must already exist in
   *   storage (i.e. is saved) or it'll be ignored. If this is null, the base
   *   product will be unset.
   */
  public function setBaseProduct(?ProductInterface $product): void;

  /**
   * Get the configured base content access product ID.
   *
   * @return string|null
   *   Either a product entity ID, or null if not configured.
   */
  public function getBaseProductId(): ?string;

  /**
   * Get the configured base content access product.
   *
   * @return \Drupal\commerce_product\Entity\ProductInterface|null
   *   Either a product entity, or null if not configured.
   */
  public function getBaseProduct(): ?ProductInterface;

  /**
   * Get the message associated with a product, if any.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   A product entity.
   *
   * @return array
   *   The message render array.
   */
  public function getProductMessage(ProductInterface $product): array;

  /**
   * Get the wiki node associated with a product, if any.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
    *   A product entity.
  *
   * @return \Drupal\omnipedia_core\Entity\NodeInterface|null
   *   Either a node entity or null if one can't be determined.
   */
  public function getProductWikiNode(ProductInterface $product): ?NodeInterface;

}
