<?php
namespace Grav\Plugin;

use Grav\Common\User\Interfaces\UserCollectionInterface;

/**
 * Trait SocialUserTrait
 * Gère les fonctionnalités liées aux utilisateurs (follow, unfollow)
 */
trait SocialUserTrait
{
    /**
     * Get accounts manager
     * @return UserCollectionInterface|null
     */
    protected function getAccounts()
    {
        return $this->grav['accounts'] ?? null;
    }

    /**
     * Follow a user (using Grav native accounts)
     */
    protected function followUser($follower, $targetUsername)
    {
        $accounts = $this->getAccounts();
        if (!$accounts) return;

        // Get both users
        $followerUser = $accounts->load($follower);
        $targetUser = $accounts->load($targetUsername);

        if (!$followerUser || !$followerUser->exists() || !$targetUser || !$targetUser->exists()) {
            return;
        }

        // Update follower's following list
        $relations = $followerUser->get('relations', []);
        $following = $relations['following'] ?? [];
        
        if (!in_array($targetUsername, $following)) {
            $following[] = $targetUsername;
            $relations['following'] = $following;
            $followerUser->set('relations', $relations);
            $followerUser->save();
        }

        // Update target's followers list
        $targetRelations = $targetUser->get('relations', []);
        $followers = $targetRelations['followers'] ?? [];
        
        if (!in_array($follower, $followers)) {
            $followers[] = $follower;
            $targetRelations['followers'] = $followers;
            $targetUser->set('relations', $targetRelations);
            $targetUser->save();
        }
    }

    /**
     * Unfollow a user
     */
    protected function unfollowUser($follower, $targetUsername)
    {
        $accounts = $this->getAccounts();
        if (!$accounts) return;

        $followerUser = $accounts->load($follower);
        $targetUser = $accounts->load($targetUsername);

        if (!$followerUser || !$followerUser->exists() || !$targetUser || !$targetUser->exists()) {
            return;
        }

        // Update follower's following list
        $relations = $followerUser->get('relations', []);
        $following = $relations['following'] ?? [];
        $following = array_filter($following, fn($u) => $u !== $targetUsername);
        $relations['following'] = array_values($following);
        $followerUser->set('relations', $relations);
        $followerUser->save();

        // Update target's followers list
        $targetRelations = $targetUser->get('relations', []);
        $followers = $targetRelations['followers'] ?? [];
        $followers = array_filter($followers, fn($u) => $u !== $follower);
        $targetRelations['followers'] = array_values($followers);
        $targetUser->set('relations', $targetRelations);
        $targetUser->save();
    }
}
