<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Utils;
use Grav\Common\User\Interfaces\UserCollectionInterface;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class SocialCorePlugin
 * Plugin central pour le réseau social de connaissance
 * Utilise les utilisateurs Grav natifs et Flex Objects pour les espaces
 * @package Grav\Plugin
 */
class SocialCorePlugin extends Plugin
{
    /**
     * Flag to prevent recursive saves
     */
    private static $isSaving = false;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 10], // Priorité plus haute
            'onFormProcessed'      => ['onFormProcessed', 0],
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        // Handle POST routes IMMEDIATELY (before page lookup)
        if (!$this->isAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $uri = $this->grav['uri'];
            $route = $uri->path();
            
            if ($route === '/social-action') {
                $this->handleSocialAction();
                return; // Stop processing
            }
            
            if ($route === '/send-message') {
                $this->handleSendMessage();
                return; // Stop processing
            }
        }
        
        // Enable admin events
        if ($this->isAdmin()) {
            $this->enable([
                'onFlexAfterSave' => ['onFlexAfterSave', 0],
                'onFlexObjectBeforeSave' => ['onFlexObjectBeforeSave', 0],
            ]);
            return;
        }

        // Enable frontend events
        $this->enable([
            'onFlexAfterSave' => ['onFlexAfterSave', 0],
            'onFlexObjectBeforeSave' => ['onFlexObjectBeforeSave', 0],
            'onTwigInitialized' => ['onTwigInitialized', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
        ]);
    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Add Twig variables - expose rooms directly from JSON file
     * Contourne les problèmes de cache Flex Objects
     */
    public function onTwigSiteVariables()
    {
        $twig = $this->grav['twig'];
        $locator = $this->grav['locator'];
        
        // Add Social Core assets
        $this->grav['assets']->addJs('plugin://social-core/assets/js/address-autocomplete.js', ['group' => 'bottom', 'defer' => true]);
        $this->grav['assets']->addCss('plugin://social-core/assets/css/address-autocomplete.css');
        $this->grav['assets']->addCss('plugin://social-core/assets/css/room-address.css');


        
        // Variables par défaut
        $rooms = [];
        $messages = [];
        $activities = [];
        $error = null;
        
        try {
            // Charger directement le fichier JSON des rooms
            $roomsFile = $locator->findResource('user://data/flex-objects/rooms.json', true);
            if ($roomsFile && file_exists($roomsFile)) {
                $content = file_get_contents($roomsFile);
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $rooms = $data;
                }
            }
            
            // Charger les messages
            $messagesFile = $locator->findResource('user://data/flex-objects/messages.json', true);
            if ($messagesFile && file_exists($messagesFile)) {
                $content = file_get_contents($messagesFile);
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $messages = $data;
                }
            }
            
            // Charger les activités
            $activitiesFile = $locator->findResource('user://data/flex-objects/activity.json', true);
            if ($activitiesFile && file_exists($activitiesFile)) {
                $content = file_get_contents($activitiesFile);
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $activities = $data;
                }
            }
            
            // Charger les demandes d'adhésion
            $membershipRequests = [];
            $requestsFile = $locator->findResource('user://data/flex-objects/membership-requests.json', true);
            if ($requestsFile && file_exists($requestsFile)) {
                $content = file_get_contents($requestsFile);
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $membershipRequests = $data;
                }
            }
            
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $this->grav['log']->error('SocialCorePlugin: ' . $error);
        }
        
        // Exposer les variables Twig
        $twig->twig_vars['rooms'] = $rooms;
        $twig->twig_vars['rooms_count'] = count($rooms);
        $twig->twig_vars['rooms_dir'] = true; // Pour compatibilité avec les templates
        $twig->twig_vars['membership_requests'] = $membershipRequests ?? [];
        
        // Messages
        $twig->twig_vars['messages'] = $messages;
        $twig->twig_vars['messages_count'] = count($messages);
        
        // Activités
        $twig->twig_vars['activities'] = $activities;
        
        // Debug info
        $twig->twig_vars['flex_loaded'] = true;
        $twig->twig_vars['flex_debug'] = [
            'rooms_count' => count($rooms),
            'messages_count' => count($messages),
            'error' => $error,
            'source' => 'direct_json'
        ];
    }

    /**
     * Register Custom Twig Functions
     */
    public function onTwigInitialized()
    {
        $this->grav['twig']->twig()->addFunction(
            new \Twig\TwigFunction('get_address_hierarchy', [$this, 'getAddressHierarchy'])
        );
    }

    /**
     * Get address hierarchy for a given tag ID
     * Returns: [RegionTag, CityTag, StreetTag, NumberTag]
     */
    public function getAddressHierarchy($tagId)
    {
        if (!$tagId) return [];

        $directory = $this->getFlexDirectory('knowledge-tags');
        if (!$directory) return [];

        $hierarchy = [];
        $currentId = $tagId;
        
        // Limit depth to avoid infinite loops
        for ($i = 0; $i < 10; $i++) {
            $tag = $directory->getObject($currentId);
            if (!$tag) break;
            
            array_unshift($hierarchy, $tag); // Add to beginning (Child -> Parent becomes Parent -> Child)
            
            $parentId = $tag->getProperty('parent');
            if (!$parentId) break;
            
            $currentId = $parentId;
        }
        
        return $hierarchy;
    }

    /**
     * Get accounts manager
     * @return UserCollectionInterface|null
     */
    protected function getAccounts()
    {
        return $this->grav['accounts'] ?? null;
    }

    /**
     * Handle social actions (follow, unfollow, join, leave)
     */
    protected function handleSocialAction()
    {
        $user = $this->grav['user'];
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        
        // Vérifier l'authentification
        if (!$user || !$user->authenticated || !$user->username) {
            $this->grav['messages']->add('Vous devez être connecté', 'error');
            $this->grav->redirect('/login');
            return;
        }

        $action = $_POST['action'] ?? '';
        $target = $_POST['target'] ?? '';
        $nonce = $_POST['social-nonce'] ?? '';

        // Vérifier le nonce
        if (!Utils::verifyNonce($nonce, 'social-action')) {
            $this->grav['messages']->add('Session expirée, veuillez réessayer', 'error');
            $this->grav->redirect($referer);
            return;
        }

        if (empty($action)) {
            $this->grav['messages']->add('Action non spécifiée', 'error');
            $this->grav->redirect($referer);
            return;
        }

        if (empty($target)) {
            $this->grav['messages']->add('Cible non spécifiée', 'error');
            $this->grav->redirect($referer);
            return;
        }

        $this->grav['log']->info("Social action: {$action} on {$target} by {$user->username}");

        try {
            switch ($action) {
                case 'follow':
                    $this->followUser($user->username, $target);
                    $this->logActivity('follow', 'user', $target);
                    $this->grav['messages']->add('Vous suivez maintenant cet utilisateur', 'success');
                    break;
                    
                case 'unfollow':
                    $this->unfollowUser($user->username, $target);
                    $this->grav['messages']->add('Vous ne suivez plus cet utilisateur', 'info');
                    break;
                    
                case 'join-space':
                    $this->joinSpace($user->username, $target);
                    $this->logActivity('join', 'space', $target);
                    $this->grav['messages']->add('Vous avez rejoint la room !', 'success');
                    break;
                    
                case 'leave-space':
                    $this->leaveSpace($user->username, $target);
                    $this->grav['messages']->add('Vous avez quitté la room', 'info');
                    break;
                
                // Système de demande d'adhésion avec vote
                case 'request-join':
                    $this->requestJoinSpace($user->username, $target);
                    break;
                    
                case 'cancel-request':
                    $this->cancelJoinRequest($user->username, $target);
                    break;
                    
                case 'vote-accept':
                    $this->voteOnRequest($user->username, $target, 'accept');
                    break;
                    
                case 'vote-reject':
                    $this->voteOnRequest($user->username, $target, 'reject');
                    break;
                    
                default:
                    $this->grav['messages']->add('Action inconnue: ' . $action, 'error');
            }
        } catch (\Exception $e) {
            $this->grav['log']->error('Social action error: ' . $e->getMessage());
            $this->grav['messages']->add('Erreur: ' . $e->getMessage(), 'error');
        }

        // Redirect back
        $this->grav->redirect($referer);
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

    /**
     * Join a space
     */
    protected function joinSpace($username, $spaceSlug)
    {
        // Charger les rooms depuis JSON
        $roomsFile = $this->grav['locator']->findResource('user://data/flex-objects/rooms.json', true, true);
        if (!$roomsFile || !file_exists($roomsFile)) {
            $this->grav['messages']->add('Erreur: impossible de charger les rooms', 'error');
            return;
        }
        
        $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
        
        if (!isset($rooms[$spaceSlug])) {
            $this->grav['messages']->add('Room introuvable', 'error');
            return;
        }
        
        // Vérifier que l'utilisateur n'est pas déjà membre
        $members = $rooms[$spaceSlug]['members'] ?? [];
        $admins = $rooms[$spaceSlug]['admins'] ?? [];
        
        if (in_array($username, $members) || in_array($username, $admins)) {
            $this->grav['messages']->add('Vous êtes déjà membre de cette room', 'warning');
            return;
        }
        
        // Ajouter l'utilisateur aux membres
        $members[] = $username;
        $rooms[$spaceSlug]['members'] = $members;
        
        // Sauvegarder
        file_put_contents($roomsFile, json_encode($rooms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->grav['log']->info("{$username} joined room {$spaceSlug}");
    }

    /**
     * Leave a space
     */
    protected function leaveSpace($username, $spaceSlug)
    {
        // Charger les rooms depuis JSON
        $roomsFile = $this->grav['locator']->findResource('user://data/flex-objects/rooms.json', true);
        if (!$roomsFile || !file_exists($roomsFile)) {
            $this->grav['messages']->add('Erreur: impossible de charger les rooms', 'error');
            return;
        }
        
        $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
        
        if (!isset($rooms[$spaceSlug])) {
            $this->grav['messages']->add('Room introuvable', 'error');
            return;
        }
        
        $room = $rooms[$spaceSlug];
        
        // Vérifier si l'utilisateur est admin (les admins ne peuvent pas quitter)
        $admins = $room['admins'] ?? [];
        if (in_array($username, $admins)) {
            $this->grav['messages']->add('Les administrateurs ne peuvent pas quitter leur room', 'warning');
            return;
        }
        
        // Retirer l'utilisateur des membres
        $members = $room['members'] ?? [];
        $members = array_values(array_filter($members, fn($m) => $m !== $username));
        $rooms[$spaceSlug]['members'] = $members;
        
        // Sauvegarder
        file_put_contents($roomsFile, json_encode($rooms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->grav['messages']->add('Vous avez quitté la room', 'info');
        $this->grav['log']->info("{$username} left room {$spaceSlug}");
    }

    /**
     * ============================================
     * SYSTÈME DE DEMANDE D'ADHÉSION AVEC VOTE
     * ============================================
     */

    /**
     * Charger les demandes d'adhésion depuis JSON
     */
    protected function loadMembershipRequests()
    {
        $file = $this->grav['locator']->findResource('user://data/flex-objects/membership-requests.json', true, true);
        if ($file && file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: [];
        }
        return [];
    }

    /**
     * Sauvegarder les demandes d'adhésion
     */
    protected function saveMembershipRequests($requests)
    {
        $file = $this->grav['locator']->findResource('user://data/flex-objects/membership-requests.json', true, true);
        file_put_contents($file, json_encode($requests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Demander à rejoindre un espace (pour les espaces privés)
     */
    protected function requestJoinSpace($username, $spaceSlug)
    {
        // Vérifier que l'espace existe
        $roomsFile = $this->grav['locator']->findResource('user://data/flex-objects/rooms.json', true);
        if (!$roomsFile || !file_exists($roomsFile)) {
            $this->grav['messages']->add('Erreur: impossible de charger les rooms', 'error');
            return;
        }
        
        $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
        $room = $rooms[$spaceSlug] ?? null;
        
        if (!$room) {
            $this->grav['messages']->add('Room introuvable', 'error');
            return;
        }
        
        // Vérifier que l'utilisateur n'est pas déjà membre
        $members = $room['members'] ?? [];
        $admins = $room['admins'] ?? [];
        if (in_array($username, $members) || in_array($username, $admins)) {
            $this->grav['messages']->add('Vous êtes déjà membre de cette room', 'warning');
            return;
        }
        
        // Charger les demandes existantes
        $requests = $this->loadMembershipRequests();
        
        // Vérifier qu'il n'y a pas déjà une demande en cours
        $requestKey = "{$spaceSlug}_{$username}";
        if (isset($requests[$requestKey])) {
            $this->grav['messages']->add('Vous avez déjà une demande en cours pour cette room', 'warning');
            return;
        }
        
        // Créer la demande
        $requests[$requestKey] = [
            'id' => $requestKey,
            'space_slug' => $spaceSlug,
            'username' => $username,
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'pending',
            'votes_accept' => [],
            'votes_reject' => []
        ];
        
        $this->saveMembershipRequests($requests);
        $this->grav['messages']->add('Demande envoyée ! Les membres vont voter.', 'success');
        
        // Log activity
        $this->logActivity('request_join', 'space', $spaceSlug);
    }

    /**
     * Annuler une demande d'adhésion
     */
    protected function cancelJoinRequest($username, $spaceSlug)
    {
        $requests = $this->loadMembershipRequests();
        $requestKey = "{$spaceSlug}_{$username}";
        
        if (!isset($requests[$requestKey])) {
            return;
        }
        
        // Seul le demandeur peut annuler sa propre demande
        if ($requests[$requestKey]['username'] !== $username) {
            return;
        }
        
        unset($requests[$requestKey]);
        $this->saveMembershipRequests($requests);
        $this->grav['messages']->add('Demande annulée', 'info');
    }

    /**
     * Voter sur une demande d'adhésion
     */
    protected function voteOnRequest($voterUsername, $requestKey, $vote)
    {
        $requests = $this->loadMembershipRequests();
        
        if (!isset($requests[$requestKey])) {
            $this->grav['messages']->add('Demande introuvable', 'error');
            return;
        }
        
        $request = $requests[$requestKey];
        $spaceSlug = $request['space_slug'];
        
        // Vérifier que le votant est membre de l'espace
        $roomsFile = $this->grav['locator']->findResource('user://data/flex-objects/rooms.json', true);
        $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
        $room = $rooms[$spaceSlug] ?? null;
        
        if (!$room) {
            return;
        }
        
        $members = $room['members'] ?? [];
        $admins = $room['admins'] ?? [];
        $allMembers = array_unique(array_merge($members, $admins));
        
        if (!in_array($voterUsername, $allMembers)) {
            $this->grav['messages']->add('Seuls les membres peuvent voter', 'error');
            return;
        }
        
        // Empêcher de voter pour sa propre demande
        if ($request['username'] === $voterUsername) {
            $this->grav['messages']->add('Vous ne pouvez pas voter pour votre propre demande', 'error');
            return;
        }
        
        // Retirer le vote précédent s'il existe
        $requests[$requestKey]['votes_accept'] = array_values(array_filter(
            $request['votes_accept'] ?? [], 
            fn($v) => $v !== $voterUsername
        ));
        $requests[$requestKey]['votes_reject'] = array_values(array_filter(
            $request['votes_reject'] ?? [], 
            fn($v) => $v !== $voterUsername
        ));
        
        // Ajouter le nouveau vote
        if ($vote === 'accept') {
            $requests[$requestKey]['votes_accept'][] = $voterUsername;
        } else {
            $requests[$requestKey]['votes_reject'][] = $voterUsername;
        }
        
        $this->saveMembershipRequests($requests);
        
        // Calculer si la majorité est atteinte
        $this->checkVoteMajority($requestKey, $requests[$requestKey], $allMembers);
        
        $this->grav['messages']->add('Vote enregistré', 'success');
    }

    /**
     * Vérifier si la majorité est atteinte et appliquer la décision
     */
    protected function checkVoteMajority($requestKey, $request, $allMembers)
    {
        $votesAccept = count($request['votes_accept'] ?? []);
        $votesReject = count($request['votes_reject'] ?? []);
        $totalMembers = count($allMembers);
        
        // Exclure le demandeur du calcul de majorité (il ne peut pas voter)
        // Donc on calcule sur totalMembers - 1 si le demandeur n'est pas encore membre
        $votingMembers = $totalMembers;
        $majority = ceil($votingMembers / 2);
        
        // Si majorité pour accepter
        if ($votesAccept >= $majority) {
            $this->acceptMembershipRequest($request);
            return;
        }
        
        // Si majorité pour refuser OU si tous ont voté et c'est refus
        if ($votesReject >= $majority) {
            $this->rejectMembershipRequest($requestKey);
            return;
        }
        
        // Si tous ont voté (égalité = refus par défaut)
        $totalVotes = $votesAccept + $votesReject;
        if ($totalVotes >= $votingMembers) {
            // Égalité ou plus de refus que d'acceptations
            $this->rejectMembershipRequest($requestKey);
        }
    }

    /**
     * Accepter une demande d'adhésion
     */
    protected function acceptMembershipRequest($request)
    {
        $username = $request['username'];
        $spaceSlug = $request['space_slug'];
        
        // Ajouter l'utilisateur comme membre directement dans le JSON
        $roomsFile = $this->grav['locator']->findResource('user://data/flex-objects/rooms.json', true, true);
        if ($roomsFile && file_exists($roomsFile)) {
            $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
            
            if (isset($rooms[$spaceSlug])) {
                $members = $rooms[$spaceSlug]['members'] ?? [];
                if (!in_array($username, $members)) {
                    $members[] = $username;
                    $rooms[$spaceSlug]['members'] = $members;
                    file_put_contents($roomsFile, json_encode($rooms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $this->grav['log']->info("Added {$username} to room {$spaceSlug} members");
                }
            }
        }
        
        // Supprimer la demande
        $requests = $this->loadMembershipRequests();
        $requestKey = "{$spaceSlug}_{$username}";
        unset($requests[$requestKey]);
        $this->saveMembershipRequests($requests);
        
        // Log activity
        $this->logActivity('accepted_join', 'space', $spaceSlug, $username);
        
        $this->grav['messages']->add("{$username} a été accepté comme membre !", 'success');
        $this->grav['log']->info("Membership request accepted: {$username} joined {$spaceSlug}");
    }

    /**
     * Refuser une demande d'adhésion
     */
    protected function rejectMembershipRequest($requestKey)
    {
        $requests = $this->loadMembershipRequests();
        
        if (!isset($requests[$requestKey])) {
            return;
        }
        
        $request = $requests[$requestKey];
        
        // Marquer comme refusée plutôt que supprimer (pour historique)
        $requests[$requestKey]['status'] = 'rejected';
        $requests[$requestKey]['resolved_at'] = date('Y-m-d H:i:s');
        $this->saveMembershipRequests($requests);
        
        $this->grav['log']->info("Membership request rejected: {$request['username']} for {$request['space_slug']}");
    }

    /**
     * Handle Flex Object save events
     */
    public function onFlexAfterSave(Event $event)
    {
        // Prevent recursive saves
        if (self::$isSaving) {
            return;
        }

        $object = $event['object'];
        $type = $object->getFlexType();

        // Handle Space-User Synchronization (only in admin)
        if ($type === 'social-spaces') {
             if ($this->isAdmin()) {
                 $this->syncSpaceMembers($object);
             }
         }
    }
    
    /**
     * Hook before saving a Flex Object
     */
    public function onFlexObjectBeforeSave(Event $event)
    {
        $object = $event['object'];
        $type = $object->getFlexType();
        
        // 1. Social Spaces (Room Creation)
        if ($type === 'social-spaces') {
             // Check if address data was submitted
            $form = $this->grav['request']->getParsedBody();
            $addressDataJson = $form['data']['address_data'] ?? null;
            
            if ($addressDataJson) {
                $addressData = json_decode($addressDataJson, true);
                if ($addressData && isset($addressData['properties'])) {
                    // Extract coordinates
                    $coordinates = $addressData['geometry']['coordinates'] ?? null;
                    
                    // Process hierarchy with coordinates
                    $tagId = $this->processAddressHierarchy($addressData['properties'], $coordinates);
                    if ($tagId) {
                        $object->setProperty('address_tag', $tagId);
                        $object->setProperty('location', $addressData['properties']['label'] ?? '');
                        if ($coordinates) {
                            $object->setProperty('longitude', $coordinates[0]);
                            $object->setProperty('latitude', $coordinates[1]);
                        }
                    }
                }
            }
            return;
        }

        // 2. Knowledge Tags (Manual Creation)
        // Auto-Geocoding if no coordinates provided
        if ($type === 'knowledge-tags') {
            $isNew = !$object->exists();
            $lat = $object->getProperty('latitude');
            $lon = $object->getProperty('longitude');
            
            // Only if new or forcing update, and has no coords
            if ((empty($lat) || empty($lon))) {
                $name = $object->getProperty('name');
                $tagType = $object->getProperty('tag_type');
                
                // Don't geocode everything, only location-relevant tags if they have a name
                if ($name && in_array($tagType, ['ville', 'rue', 'numero'])) {
                    // Build query
                    $query = $name;
                    $parent = $object->getProperty('parent');
                    if ($parent) {
                        $directory = $this->getFlexDirectory('knowledge-tags');
                        $parentObj = $directory ? $directory->getObject($parent) : null;
                        if ($parentObj) {
                            $query .= ' ' . $parentObj->getProperty('name');
                        }
                    }
                    
                    // Call API
                    $url = "https://api-adresse.data.gouv.fr/search/?q=" . urlencode($query) . "&limit=1";
                    try {
                        $response = file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 2]]));
                        if ($response) {
                            $json = json_decode($response, true);
                            if (!empty($json['features'])) {
                                $feat = $json['features'][0];
                                $coords = $feat['geometry']['coordinates'] ?? null;
                                if ($coords) {
                                    $object->setProperty('longitude', $coords[0]);
                                    $object->setProperty('latitude', $coords[1]);
                                    
                                    // Also set postcode/citycode if available and empty
                                    if (!$object->getProperty('postcode')) {
                                        $object->setProperty('postcode', $feat['properties']['postcode'] ?? '');
                                    }
                                    if (!$object->getProperty('citycode')) {
                                        $object->setProperty('citycode', $feat['properties']['citycode'] ?? '');
                                    }
                                    
                                    $this->grav['log']->info("Auto-geocoded tag: {$name} -> {$coords[1]}, {$coords[0]}");
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignore geocoding errors to not block save
                        $this->grav['log']->warning("Geocoding failed for {$name}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Process address hierarchy and return the leaf node ID (Number or Street)
     */
    /**
     * Process address hierarchy and return the leaf node ID (Number or Street)
     * Strictly enforces: Region -> City -> Street -> Number
     */
    public function processAddressHierarchy($props, $coordinates = null)
    {
        $directory = $this->getFlexDirectory('knowledge-tags');
        if (!$directory) return null;

        // 0. Country (Racine fixe pour API Adresse qui est France uniquement)
        $countryName = 'France';
        $countryTag = $this->findOrCreateTag($directory, $countryName, 'pays', null, [
            'description' => 'Pays'
        ]);

        // 1. Region / Department (Enfant de Pays)
        // Utlise 'context' (ex: "Somme, Hauts-de-France") ou 'citycode' (ex: 80021 -> 80)
        $regionName = $props['context'] ?? substr($props['citycode'] ?? '00000', 0, 2); 
        $regionTag = $this->findOrCreateTag($directory, $regionName, 'region', $countryTag->getKey(), [
            'description' => 'Région / Département auto-généré'
        ]);

        // 2. City (Enfant de Region)
        $cityName = $props['city'] ?? 'Ville Inconnue';
        $cityTag = $this->findOrCreateTag($directory, $cityName, 'ville', $regionTag->getKey(), [
            'citycode' => $props['citycode'] ?? '',
            'postcode' => $props['postcode'] ?? '',
            'description' => $props['city'] ?? ''
        ]);

        // 3. Street (Enfant de City)
        $streetName = $props['street'] ?? $props['name'] ?? 'Rue Inconnue';
        // Si pas de numéro, l'objet s'arrête ici
        $streetTag = $this->findOrCreateTag($directory, $streetName, 'rue', $cityTag->getKey(), [
            'description' => $streetName
        ]);

        // 4. Number (Enfant de Street, facultatif)
        if (!empty($props['housenumber'])) {
            $numberTag = $this->findOrCreateTag($directory, $props['housenumber'], 'numero', $streetTag->getKey(), [
                 'latitude' => $coordinates ? $coordinates[1] : ($props['y'] ?? $props['latitude'] ?? null),
                 'longitude' => $coordinates ? $coordinates[0] : ($props['x'] ?? $props['longitude'] ?? null),
                 'description' => $props['label'] ?? ''
            ]);
            return $numberTag->getKey();
        }

        return $streetTag->getKey();
    }

    /**
     * Helper to find or create a tag
     * Ensures uniqueness by Parent + Slug strategy
     */
    protected function findOrCreateTag($directory, $name, $type, $parentId = null, $extraData = [])
    {
        if (empty($name)) return null;

        // Génération d'un slug unique basé sur le parent pour éviter les collisions (ex: Rue de la Paix à Paris vs Amiens)
        // Slug = type-slug(name)-hash(parent)
        $parentHash = $parentId ? substr(md5($parentId), 0, 5) : 'root';
        $slugBase = self::staticSlugify($name);
        
        // Optimisation : slug court si racine, long si enfant
        $uniqueSlug = $parentId ? "{$type}-{$slugBase}-{$parentHash}" : self::staticSlugify("{$type}-{$name}");
        
        // Tentative de récupération directe
        $object = $directory->getObject($uniqueSlug);
        
        if ($object) {
            return $object;
        }

        // Création si inexistant
        try {
            $data = array_merge([
                'name' => $name,
                'slug' => $uniqueSlug,
                'tag_type' => $type,
                'parent' => $parentId, // Lien de parenté
                'published' => true
            ], $extraData);

            $object = $directory->createObject($data, $uniqueSlug);
            $object->save();
            
            return $object;
        } catch (\Exception $e) {
            // Fallback si erreur de concurrence (double création)
            $this->grav['log']->warning("Tag creation collision handled for: $uniqueSlug");
            return $directory->getObject($uniqueSlug);
        }
    }

    /**
     * Static method to provide options for existing tags (used in blueprints)
     */
    public static function getKnowledgeTagsOptions()
    {
        $grav = \Grav\Common\Grav::instance();
        $directory = $grav['flex']->getDirectory('knowledge-tags');
        if (!$directory) return [];
        
        $options = [];
        $collection = $directory->getCollection();
        
        foreach ($collection as $object) {
            $type = $object->getProperty('tag_type') ?? 'general';
            $name = $object->getProperty('name');
            $parent = $object->getProperty('parent');
            
            $label = "[$type] $name";
            
            // Add parent info for context
            if ($parent) {
                $parentObj = $directory->getObject($parent);
                if ($parentObj) {
                    $label .= " ( < " . $parentObj->getProperty('name') . " )";
                }
            }
            
            $options[$object->getKey()] = $label;
        }
        
        return $options;
    }

    /**
     * Synchronize users when space members are updated
     */
    protected function syncSpaceMembers($space)
    {
        $spaceSlug = $space['slug'] ?? null;
        if (!$spaceSlug) return;

        $members = $space['members'] ?? [];
        $accounts = $this->getAccounts();
        
        if (!$accounts) return;

        self::$isSaving = true;
        
        try {
            // Ensure all members have this space in their list
            foreach ($members as $username) {
                $user = $accounts->load($username);
                if ($user && $user->exists()) {
                    $relations = $user->get('relations', []);
                    $userSpaces = $relations['spaces'] ?? [];
                    
                    if (!in_array($spaceSlug, $userSpaces)) {
                        $userSpaces[] = $spaceSlug;
                        $relations['spaces'] = $userSpaces;
                        $user->set('relations', $relations);
                        $user->save();
                    }
                }
            }
        } finally {
            self::$isSaving = false;
        }
    }

    /**
     * Log activity to the activity-stream
     */
    protected function logActivity($verb, $type, $id, $context = null)
    {
        $activityDirectory = $this->getFlexDirectory('activity-stream');
        if (!$activityDirectory) return;

        $user = $this->grav['user'];
        
        try {
            $activity = $activityDirectory->createObject([
                'timestamp' => date('Y-m-d H:i:s'),
                'actor' => $user->username ?: 'system',
                'verb' => $verb,
                'object_type' => $type,
                'object_id' => $id,
                'context' => $context
            ]);
            $activity->save();
        } catch (\Exception $e) {
            // Silent fail for activity logging
            $this->grav['log']->warning('Activity logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle Front-end Form Submissions
     */
    public function onFormProcessed(Event $event)
    {
        $form = $event['form'];
        $action = $event['action'] ?? '';

        // Gestion via process.call
        if ($action === 'call') {
            return; // Géré par la méthode statique
        }

        if ($form->name === 'create_space_form') {
            $this->handleSpaceCreation($form);
        }

        if ($form->name === 'create-room') {
            $this->handleRoomCreationFromForm($form);
        }
    }

    /**
     * Static method for form process call (Grav best practice)
     * Called by: process: - call: ['\Grav\Plugin\SocialCorePlugin', 'processCreateRoom']
     * 
     * Utilise SimpleStorage avec fichier JSON unique
     */
    /**
     * Instance method for room creation
     * Called by onFormProcessed via handleRoomCreationFromForm
     * 
     * Utilise SimpleStorage avec fichier JSON unique
     */
    public function processCreateRoom($form)
    {
        $grav = $this->grav;
        $user = $grav['user'];
        
        // Check authentication
        if (!$user || !$user->authenticated || !$user->username) {
            $grav['messages']->add('Vous devez être connecté pour créer une room', 'error');
            $grav->redirect('/login?redirect=/create-room');
            return;
        }

        // Get form data
        $data = $form->value()->toArray();
        $name = trim($data['name'] ?? '');
        
        if (empty($name) || strlen($name) < 3) {
            $grav['messages']->add('Le nom de la room doit contenir au moins 3 caractères', 'error');
            return;
        }

        // Generate slug (sécurisé)
        $slug = self::staticSlugify($name);
        
        // Validation du slug
        if (empty($slug) || $slug === '{KEY}' || strpos($slug, '{') !== false) {
            $slug = 'room-' . substr(uniqid(), -8);
        }
        
        $description = trim($data['description'] ?? '');
        $accessLevel = $data['access_level'] ?? 'public';
        $location = trim($data['location'] ?? '');

        try {
            // Chemin du fichier JSON des rooms (SimpleStorage)
            $jsonFile = $grav['locator']->findResource('user://data/flex-objects/rooms.json', true, true);
            
            if (!$jsonFile) {
                // Créer le fichier s'il n'existe pas
                $flexFolder = $grav['locator']->findResource('user://data/flex-objects', true, true);
                if (!$flexFolder) {
                    throw new \RuntimeException('Dossier flex-objects introuvable');
                }
                $jsonFile = $flexFolder . '/rooms.json';
            }
            
            // Lire les données existantes
            $rooms = [];
            if (file_exists($jsonFile)) {
                $content = file_get_contents($jsonFile);
                $rooms = json_decode($content, true) ?: [];
            }
            
            // Verify slug uniqueness
            if (isset($rooms[$slug])) {
                $slug = $slug . '-' . substr(uniqid(), -6);
            }

            // Process Address Data
            $addressTagId = null;
            $locationData = $data['address_data'] ?? null;
            
            if ($locationData) {
                $addressProps = json_decode($locationData, true);
                if ($addressProps && isset($addressProps['properties'])) {
                    // Call plugin instance method directly using $this
                    $addressTagId = $this->processAddressHierarchy($addressProps['properties']);
                    
                    // Update location with formatted label
                    $location = $addressProps['properties']['label'] ?? $location;
                }
            }

            // Room Data
            $roomData = [
                'name' => $name,
                'description' => $description,
                'slug' => $slug,
                'admins' => [$user->username],
                'members' => [$user->username],
                'access_level' => $accessLevel,
                'location' => $location,
                'address_tag' => $addressTagId,
                'created' => date('Y-m-d H:i:s')
            ];

            // Ajouter la room au fichier JSON
            $rooms[$slug] = $roomData;
            
            // Écrire le fichier JSON
            $written = file_put_contents($jsonFile, json_encode($rooms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            if ($written === false) {
                throw new \RuntimeException('Impossible d\'écrire le fichier: ' . $jsonFile);
            }
            
            $grav['log']->info('Room added to JSON: ' . $slug);

            // Update user relations
            $accounts = $grav['accounts'] ?? null;
            if ($accounts) {
                $userObj = $accounts->load($user->username);
                if ($userObj) {
                    $relations = $userObj->get('relations', []);
                    $userSpaces = $relations['spaces'] ?? [];
                    if (!in_array($slug, $userSpaces)) {
                        $userSpaces[] = $slug;
                        $relations['spaces'] = $userSpaces;
                        $userObj->set('relations', $relations);
                        $userObj->save();
                    }
                }
            }
            
            $grav['messages']->add('Room "' . $name . '" créée avec succès !', 'success');
            $grav->redirect('/room/' . $slug);
            return;
            
        } catch (\Exception $e) {
            $grav['log']->error('Error creating room: ' . $e->getMessage());
            $grav['messages']->add('Erreur lors de la création de la room: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Clear a directory recursively
     */
    protected static function clearDirectory($dir)
    {
        if (!is_dir($dir)) return;
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }
    }

    /**
     * Static slugify helper
     */
    protected static function staticSlugify($text)
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = trim($text, '-');
        $text = strtolower($text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        return $text ?: 'room-' . substr(uniqid(), -6);
    }

    /**
     * Handle room creation from Grav form (backup method)
     * Utilise l'écriture directe de fichier YAML
     */
    protected function handleRoomCreationFromForm($form)
    {
        // Déléguer à la méthode d'instance
        $this->processCreateRoom($form);
    }

    /**
     * Create a new space from form data
     */
    protected function handleSpaceCreation($form)
    {
        $data = $form->value()->toArray();
        $username = $this->grav['user']->username;

        if (!$username) {
            throw new \RuntimeException('Vous devez être connecté pour créer un espace.');
        }

        $directory = $this->getFlexDirectory('social-spaces');
        if (!$directory) {
            throw new \RuntimeException('Le répertoire des espaces n\'est pas configuré.');
        }

        $slug = $this->slugify($data['space_name']);

        // Check if slug already exists
        $existing = $directory->getObject($slug);
        if ($existing) {
            $slug = $slug . '-' . substr(md5(time()), 0, 6);
        }

        $object = $directory->createObject([
            'name' => $data['space_name'],
            'description' => $data['space_description'] ?? '',
            'slug' => $slug,
            'admins' => [$username],
            'members' => [$username],
            'published' => true,
            'access_level' => 'public'
        ]);

        $object->save();

        // Add space to user's spaces
        $accounts = $this->getAccounts();
        if ($accounts) {
            $user = $accounts->load($username);
            if ($user && $user->exists()) {
                $relations = $user->get('relations', []);
                $userSpaces = $relations['spaces'] ?? [];
                $userSpaces[] = $slug;
                $relations['spaces'] = $userSpaces;
                $user->set('relations', $relations);
                $user->save();
            }
        }

        // Log activity
        $this->logActivity('create', 'space', $slug);

        // Redirect to the new space
        $this->grav->redirect('/room/' . $slug);
    }

    /**
     * Handle sending messages
     */
    protected function handleSendMessage()
    {
        $user = $this->grav['user'];
        
        if (!$user || !$user->authenticated || !$user->username) {
            $this->grav->redirect('/login');
            return;
        }

        $channelType = $_POST['channel_type'] ?? 'global';
        $channelId = $_POST['channel_id'] ?? 'general';
        $content = trim($_POST['content'] ?? '');
        
        // Déterminer la redirection selon le type de canal
        // space_external et space_internal redirigent vers /room/{id}
        $isSpaceChannel = in_array($channelType, ['space', 'space_external', 'space_internal']);
        $redirectUrl = $isSpaceChannel ? "/room/{$channelId}" : "/messages?type={$channelType}&channel={$channelId}";

        if (empty($content)) {
            $this->grav->redirect($redirectUrl);
            return;
        }

        // Verify nonce
        $nonce = $_POST['message-nonce'] ?? '';
        if (!Utils::verifyNonce($nonce, 'send-message')) {
            $this->grav['messages']->add('Session expirée, veuillez réessayer', 'error');
            $this->grav->redirect($redirectUrl);
            return;
        }

        // Verify access based on channel type
        if ($isSpaceChannel) {
            $roomsFile = $this->grav['locator']->findResource('user://data/flex-objects/rooms.json', true);
            if ($roomsFile && file_exists($roomsFile)) {
                $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
                $room = $rooms[$channelId] ?? null;
                
                if (!$room) {
                    $this->grav['messages']->add('Room introuvable', 'error');
                    $this->grav->redirect('/explore');
                    return;
                }
                
                $members = $room['members'] ?? [];
                $admins = $room['admins'] ?? [];
                $isMember = in_array($user->username, $members) || in_array($user->username, $admins);
                
                // space_internal : SEULS les membres peuvent écrire
                if ($channelType === 'space_internal' && !$isMember) {
                    $this->grav['messages']->add('Vous devez être membre pour écrire dans le chat interne', 'error');
                    $this->grav->redirect($redirectUrl);
                    return;
                }
                
                // space_external : Tout utilisateur connecté peut écrire (déjà vérifié au début)
                // Pas de restriction supplémentaire
            }
        }

        try {
            // Écrire directement dans le fichier JSON des messages
            $messagesFile = $this->grav['locator']->findResource('user://data/flex-objects/messages.json', true, true);
            
            $messages = [];
            if (file_exists($messagesFile)) {
                $messages = json_decode(file_get_contents($messagesFile), true) ?: [];
            }
            
            // Créer le message
            $msgId = uniqid('msg_');
            $messages[$msgId] = [
                'id' => $msgId,
                'channel_type' => $channelType,
                'channel_id' => $channelId,
                'sender' => $user->username,
                'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
                'timestamp' => date('Y-m-d H:i:s'),
                'read_by' => [$user->username]
            ];
            
            // Sauvegarder
            file_put_contents($messagesFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $this->grav['log']->info("Message sent by {$user->username} to {$channelType}/{$channelId}");

        } catch (\Exception $e) {
            $this->grav['log']->error('Message send error: ' . $e->getMessage());
            $this->grav['messages']->add('Erreur lors de l\'envoi', 'error');
        }

        $this->grav->redirect($redirectUrl);
    }

    /**
     * Set a flash message for display
     */
    protected function setFlashMessage($type, $message)
    {
        $messages = $this->grav['messages'] ?? null;
        if ($messages) {
            $messages->add($message, $type);
        }
    }

    /**
     * Get Flex directory with fallback
     */
    protected function getFlexDirectory($name)
    {
        $flex = $this->grav['flex'] ?? $this->grav['flex_objects'] ?? null;
        if (!$flex) return null;
        
        return $flex->getDirectory($name);
    }

    /**
     * Get list of active spaces (for selectize fields in Admin)
     */
    public static function listActiveSpaces()
    {
        $grav = \Grav\Common\Grav::instance();
        $flex = $grav['flex'] ?? $grav['flex_objects'] ?? null;
        if (!$flex) return [];

        $directory = $flex->getDirectory('social-spaces');
        if (!$directory) return [];

        $options = [];
        foreach ($directory->getCollection() as $space) {
            $options[$space['slug']] = $space['name'];
        }

        return $options;
    }

    /**
     * Create a URL-friendly slug from a string
     * @param string $text The text to slugify
     * @return string The slugified string
     */
    protected function slugify($text)
    {
        // Transliterate accents
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        // Replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        // Trim
        $text = trim($text, '-');
        // Lowercase
        $text = strtolower($text);
        // Remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);
        
        return $text ?: 'untitled';
    }
}
