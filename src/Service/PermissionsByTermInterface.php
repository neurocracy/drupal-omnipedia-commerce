<?php

namespace Drupal\omnipedia_commerce\Service;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * The Omnipedia Permissions by Term helper service interface.
 */
interface PermissionsByTermInterface {

  /**
   * Get access result determining if a user can update another's permissions.
   *
   * @param \Drupal\Core\Session\AccountInterface $userPerformingUpdate
   *   The user attempting to perform the update on $userToUpdate.
   *
   * @param \Drupal\Core\Session\AccountInterface $userToUpdate
   *   The user that $userPerformingUpdate is attempting to update.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   */
  public function userPermissionsUpdateAccessResult(
    ?AccountInterface $userPerformingUpdate,
    ?AccountInterface $userToUpdate
  ): AccessResultInterface;

  /**
   * Add provided terms to the user's permissions.
   *
   * @param string $uid
   *   A user ID.
   *
   * @param array $tids
   *   One or more term IDs (tids) to add to the provided user.
   *
   * @param bool|boolean $rebuild
   *   Whether to rebuild node access permissions after adding terms to the
   *   user. If this is false, node access records will not be rebuilt, in which
   *   case the caller must ensure that rebuildNodeAccess() is called after
   *   whatever batch processing has completed. Defaults to true.
   */
  public function addUserTerms(
    string $uid, array $tids, bool $rebuild = true
  ): void;

  /**
   * Rebuild node access records for a provided user.
   *
   * This should only be called if $rebuild was passed as false to
   * addUserTerms().
   *
   * @param string $uid
   *   A user ID.
   */
  public function rebuildNodeAccess(string $uid): void;

}
