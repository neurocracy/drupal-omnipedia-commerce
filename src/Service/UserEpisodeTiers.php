<?php

namespace Drupal\omnipedia_commerce\Service;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\omnipedia_access\Service\PermissionsByTermInterface;
use Drupal\omnipedia_commerce\Service\UserEpisodeTiersInterface;
use Psr\Log\LoggerInterface;

/**
 * The Omnipedia user episode tiers service.
 */
class UserEpisodeTiers implements UserEpisodeTiersInterface {

  /**
   * Our logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $loggerChannel;

  /**
   * The Omnipedia Permissions by Term helper service.
   *
   * @var \Drupal\omnipedia_access\Service\PermissionsByTermInterface
   */
  protected $permissionsByTerm;

  /**
   * Service constructor; saves dependencies.
   *
   * @param \Psr\Log\LoggerInterface $loggerChannel
   *   Our logger channel.
   *
   * @param \Drupal\omnipedia_access\Service\PermissionsByTermInterface $permissionsByTerm
   *   The Omnipedia Permissions by Term helper service.
   */
  public function __construct(
    LoggerInterface             $loggerChannel,
    PermissionsByTermInterface  $permissionsByTerm
  ) {
    $this->loggerChannel      = $loggerChannel;
    $this->permissionsByTerm  = $permissionsByTerm;
  }

  /**
   * {@inheritdoc}
   */
  public function grantUserProductEpisodeTiers(
    AccountInterface $user, ProductInterface $product
  ): void {

    // Bail if this is not an authenticated user and log an error.
    if ($user->isAnonymous()) {
      $this->loggerChannel->error(
        'User is anonymous - this could indicate a misconfiguration of commerce functionality or user permissions.'
      );

      return;
    }

    // Bail if the user is anonymous or the provided product does not have the
    // episode tiers field.
    if (!$product->hasField('field_episode_tier')) {

      $this->loggerChannel->error(
        'Product does not have the episode tiers field:<pre>@product</pre>',
        ['@product' => \print_r($product, true)]
      );

      return;

    }

    /** @var int[] Term IDs (tids) to assign the user as permissions. */
    $tids = [];

    foreach ($product->get('field_episode_tier') as $fieldItem) {

      /** @var string|null The term ID (tid) for this single episode tier or null if not set. */
      $tid = $fieldItem->target_id;

      // Skip this term ID (tid) if we got null or if it's already in $tids.
      if (empty($tid) || \in_array((int) $tid, $tids)) {
        continue;
      }

      $tids[] = (int) $tid;

    }

    $this->permissionsByTerm->addUserTerms($user->id(), $tids);

  }

}
