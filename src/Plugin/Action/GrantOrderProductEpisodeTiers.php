<?php

namespace Drupal\omnipedia_commerce\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\omnipedia_commerce\Service\PermissionsByTermInterface;
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
   * The Omnipedia Permissions by Term helper service.
   *
   * @var \Drupal\omnipedia_commerce\Service\PermissionsByTermInterface
   */
  protected $permissionsByTerm;

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
   * @param \Drupal\omnipedia_commerce\Service\PermissionsByTermInterface $permissionsByTerm
   *   The Omnipedia Permissions by Term helper service.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The Drupal tempstore factory.
   */
  public function __construct(
    array $configuration, $pluginId, $pluginDefinition,
    AccountInterface            $currentUser,
    PermissionsByTermInterface  $permissionsByTerm,
    PrivateTempStoreFactory     $tempStoreFactory
  ) {

    parent::__construct(
      $configuration, $pluginId, $pluginDefinition
    );

    $this->currentUser        = $currentUser;
    $this->permissionsByTerm  = $permissionsByTerm;
    $this->tempStoreFactory   = $tempStoreFactory;

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
      $container->get('omnipedia_commerce.permissions_by_term'),
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(
    $order, AccountInterface $account = null, $returnAsObject = false
  ) {

    /** @var \Drupal\Core\Access\AccessResultInterface */
    $access = $this->permissionsByTerm->userPermissionsUpdateAccessResult(
      $account,
      $order->getCustomer()
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
