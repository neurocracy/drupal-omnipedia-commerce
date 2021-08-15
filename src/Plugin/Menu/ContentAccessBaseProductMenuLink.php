<?php

namespace Drupal\omnipedia_commerce\Plugin\Menu;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Url;
use Drupal\omnipedia_commerce\Service\ContentAccessProductInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents a menu link to the base content access product.
 *
 * Note that this doesn't do any access checking as that's handled by the
 * product entity route, and this menu link is automatically hidden by Drupal if
 * the user does not have access to that route.
 */
class ContentAccessBaseProductMenuLink extends MenuLinkDefault implements RefinableCacheableDependencyInterface {

  use RefinableCacheableDependencyTrait;

  /**
   * The name of the product entity field containing the menu link title.
   */
  protected const MENU_LINK_TITLE_FIELD_NAME = 'field_menu_link_title';

  /**
   * The Omnipedia content access product service.
   *
   * @var \Drupal\omnipedia_commerce\Service\ContentAccessProductInterface
   */
  protected $contentAccessProduct;

  /**
   * The product entity, if any.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface|null
   */
  protected $product;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\omnipedia_commerce\Service\ContentAccessProductInterface $contentAccessProduct
   *   The Omnipedia content access product service.
   */
  public function __construct(
    array $configuration,
    $pluginID,
    $pluginDefinition,
    StaticMenuLinkOverridesInterface  $staticOverride,
    ContentAccessProductInterface     $contentAccessProduct
  ) {
    parent::__construct(
      $configuration,
      $pluginID,
      $pluginDefinition,
      $staticOverride
    );

    $this->contentAccessProduct = $contentAccessProduct;

    /** @var \Drupal\commerce_product\Entity\ProductInterface|null */
    $this->product = $this->contentAccessProduct->getBaseProduct();

    // Add the product as a cacheable dependency if it's available.
    if (\is_object($this->product)) {
      $this->addCacheableDependency($this->product);
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginID,
    $pluginDefinition
  ) {
    return new static(
      $configuration,
      $pluginID,
      $pluginDefinition,
      $container->get('menu_link.static.overrides'),
      $container->get('omnipedia_commerce.content_access_product')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {

    if (
      !\is_object($this->product) ||
      !$this->product->hasField(self::MENU_LINK_TITLE_FIELD_NAME)
    ) {
      return 'No product title';
    }

    return $this->product->get(
      self::MENU_LINK_TITLE_FIELD_NAME
    )->get(0)->getString();

  }

  /**
   * {@inheritdoc}
   */
  public function getUrlObject($titleAttribute = true) {

    if (\is_object($this->product)) {
      return $this->product->toUrl();
    }

    return Url::fromRoute('<nolink>');

  }

}
