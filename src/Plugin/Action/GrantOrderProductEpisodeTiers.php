<?php

namespace Drupal\omnipedia_commerce\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\omnipedia_commerce\Service\UserEpisodeTiersInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Commerce Order action to grant product episode tiers to a user.
 *
 * @Action(
 *   id       = "omnipedia_commerce_grant_order_product_episode_tiers",
 *   label    = @Translation("Grant product episode tiers"),
 *   type     = "commerce_order",
 *   category = @Translation("Omnipedia"),
 * )
 */
class GrantOrderProductEpisodeTiers extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The Commerce product entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $productStorage;

  /**
   * The Omnipedia user episode tiers service.
   *
   * @var \Drupal\omnipedia_commerce\Service\UserEpisodeTiersInterface
   */
  protected $userEpisodeTiers;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $productStorage
   *   The Commerce product entity storage.
   *
   * @param \Drupal\omnipedia_commerce\Service\UserEpisodeTiersInterface $userEpisodeTiers
   *   The Omnipedia user episode tiers service.
   */
  public function __construct(
    array $configuration, $pluginId, $pluginDefinition,
    EntityStorageInterface    $productStorage,
    UserEpisodeTiersInterface $userEpisodeTiers
  ) {

    parent::__construct(
      $configuration, $pluginId, $pluginDefinition
    );

    $this->productStorage   = $productStorage;
    $this->userEpisodeTiers = $userEpisodeTiers;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration, $pluginId, $pluginDefinition
  ) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('entity_type.manager')->getStorage('commerce_product'),
      $container->get('omnipedia_commerce.user_episode_tiers')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(
    $order, AccountInterface $account = null, $returnAsObject = false
  ) {

    $access = $order->getCustomer()->access('update', $account, true)->andIf(
      // Permissions by Term does not have a specific permission for updating
      // a user's permission terms, so this is the closest thing.
      AccessResult::allowedIfHasPermission(
        $account, 'show term permissions on user edit page'
      )
    );

    return $returnAsObject ? $access : $access->isAllowed();

  }

  /**
   * {@inheritdoc}
   */
  public function execute($order = null) {

    if (!\is_object($order)) {
      return;
    }

    if (!$order->hasItems()) {
      return;
    }

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

      $this->userEpisodeTiers->grantUserProductEpisodeTiers(
        $order->getCustomer(), $product
      );

    }

  }

}
