<?php

namespace Drupal\omnipedia_commerce\Service;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\omnipedia_commerce\Service\ContentAccessProductInterface;
use Drupal\omnipedia_core\Entity\NodeInterface;
use Drupal\omnipedia_core\Service\WikiNodeMainPageInterface;

/**
 * The Omnipedia content access product service.
 */
class ContentAccessProduct implements ContentAccessProductInterface {

  /**
   * The configuration name we read and write products to.
   */
  protected const CONFIG_NAME = 'omnipedia_commerce.settings';

  /**
   * The name of the product entity field containing the message.
   */
  protected const MESSAGE_FIELD_NAME = 'field_redirect_message';

  /**
   * The name of the product entity field containing a wiki node reference.
   */
  protected const WIKI_NODE_FIELD_NAME = 'field_redirect_wiki_page';

  /**
   * The Drupal configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Drupal node entity storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

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
   * The Omnipedia wiki node main page service.
   *
   * @var \Drupal\omnipedia_core\Service\WikiNodeMainPageInterface
   */
  protected $wikiNodeMainPage;

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
    EntityTypeManagerInterface  $entityTypeManager,
    WikiNodeMainPageInterface   $wikiNodeMainPage
  ) {
    $this->configFactory      = $configFactory;
    $this->nodeStorage        = $entityTypeManager->getStorage('node');
    $this->productStorage     = $entityTypeManager->getStorage(
      'commerce_product'
    );
    $this->productEntityType  = $this->productStorage->getEntityType();
    $this->wikiNodeMainPage   = $wikiNodeMainPage;
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
    )->accessCheck(true)->count();

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

  /**
   * {@inheritdoc}
   */
  public function getProductMessage(ProductInterface $product): array {

    if (!$product->hasField(self::MESSAGE_FIELD_NAME)) {
      return [];
    }

    return $product->get(self::MESSAGE_FIELD_NAME)->view([
      'label' => 'hidden',
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function getProductWikiNode(
    ProductInterface $product
  ): ?NodeInterface {

    /** @var \Drupal\Core\TypedData\TypedDataInterface|null */
    $fieldItem = $product->get(self::WIKI_NODE_FIELD_NAME)->get(0);

    if (!empty($fieldItem->target_id)) {
      $nid = $fieldItem->target_id;

    } else {
      $nid = null;
    }

    if ($nid !== null) {
      /** @var \Drupal\omnipedia_core\Entity\NodeInterface|null */
      $node = $this->nodeStorage->load($nid);
    }

    // Fall back to the default main page if no wiki node was set.
    if (!isset($node) || !\is_object($node)) {
      /** @var \Drupal\omnipedia_core\Entity\NodeInterface|null */
      $node = $this->wikiNodeMainPage->getMainPage('default');
    }

    return $node;

  }

}
