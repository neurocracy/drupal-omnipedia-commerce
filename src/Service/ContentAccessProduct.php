<?php

namespace Drupal\omnipedia_commerce\Service;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\omnipedia_commerce\Service\ContentAccessProductInterface;

/**
 * The Omnipedia content access product service.
 */
class ContentAccessProduct implements ContentAccessProductInterface {

  /**
   * The configuration name we read and write products to.
   */
  protected const CONFIG_NAME = 'omnipedia_commerce.settings';

  /**
   * The Drupal configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Commerce product entity type definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $productEntityType;

  /**
   * The Commerce product entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $productStorage;

  /**
   * Service constructor; saves dependencies.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Drupal configuration factory service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Drupal entity type plug-in manager.
   */
  public function __construct(
    ConfigFactoryInterface      $configFactory,
    EntityTypeManagerInterface  $entityTypeManager
  ) {
    $this->configFactory      = $configFactory;
    $this->productStorage     = $entityTypeManager->getStorage(
      'commerce_product'
    );
    $this->productEntityType  = $this->productStorage->getEntityType();
  }

  /**
   * {@inheritdoc}
   */
  public function setBaseProductId(?string $productId): void {

    if ($productId === null) {
      $this->configFactory->getEditable(self::CONFIG_NAME)->set(
        'base_content_access_product_id', ''
      )->save();

      return;
    }

    /** @var \Drupal\Core\Entity\Query\QueryInterface An entity query instance to verify that the provided $productId exists in storage. */
    $query = ($this->productStorage->getQuery())->condition(
      $this->productEntityType->getKeys()['id'], $productId
    )->count();

    // Bail if the provided $productId does not exist.
    if (empty($query->execute())) {
      return;
    }

    $this->configFactory->getEditable(self::CONFIG_NAME)->set(
      'base_content_access_product_id', $productId
    )->save();

  }

  /**
   * {@inheritdoc}
   */
  public function setBaseProduct(?ProductInterface $product): void {
    $this->setBaseProductId(
      \is_object($product) ? $product->id() : null
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseProductId(): ?string {
    return $this->configFactory->get(self::CONFIG_NAME)->get(
      'base_content_access_product_id'
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseProduct(): ?ProductInterface {

    /** @var string|null */
    $productId = $this->getBaseProductId();

    if ($productId === null) {
      return null;
    }

    return $this->productStorage->load($productId);

  }

}
