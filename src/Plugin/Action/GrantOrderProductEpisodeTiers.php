<?php

namespace Drupal\omnipedia_commerce\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Commerce Order action to grant product episode tiers to a user.
 *
 * @Action(
 *   id       = "omnipedia_commerce_grant_order_product_episode_tiers",
 *   label    = @Translation("Grant product episode tiers"),
 *   type     = "commerce_order",
 *   category = @Translation("Omnipedia"),
 *   confirm_form_route_name = "omnipedia_commerce.multiple_grant_order_episode_tiers_confirm",
 * )
 */
class GrantOrderProductEpisodeTiers extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The Drupal tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The Drupal tempstore factory.
   */
  public function __construct(
    array $configuration, $pluginId, $pluginDefinition,
    AccountInterface        $currentUser,
    PrivateTempStoreFactory $tempStoreFactory
  ) {

    parent::__construct(
      $configuration, $pluginId, $pluginDefinition
    );

    $this->currentUser      = $currentUser;
    $this->tempStoreFactory = $tempStoreFactory;

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
      $container->get('current_user'),
      $container->get('tempstore.private')
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
  public function executeMultiple(array $orders) {
    $this->tempStoreFactory->get('grant_order_episode_tiers_confirm')->set(
      $this->currentUser->id(), $orders
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($order = null) {
    $this->executeMultiple([$order]);
  }

}
