<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Utils;
use RocketTheme\Toolbox\Event\Event;

// Manual inclusion of Traits (since no strict autoloading for traits folder)
require_once __DIR__ . '/traits/SocialUtilsTrait.php';
require_once __DIR__ . '/traits/SocialUserTrait.php';
require_once __DIR__ . '/traits/SocialMessageTrait.php';
require_once __DIR__ . '/traits/SocialTagTrait.php';
require_once __DIR__ . '/traits/SocialVoteTrait.php';
require_once __DIR__ . '/traits/SocialSpaceTrait.php';

/**
 * Class SocialCorePlugin
 * Plugin central pour le réseau social de connaissance
 * Modularized via Traits
 */
class SocialCorePlugin extends Plugin
{
    use SocialUtilsTrait;
    use SocialUserTrait;
    use SocialMessageTrait;
    use SocialTagTrait;
    use SocialVoteTrait;
    use SocialSpaceTrait;

    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 10], 
            'onFormProcessed'      => ['onFormProcessed', 0],
        ];
    }

    public function onPluginsInitialized()
    {
        if (!$this->isAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $uri = $this->grav['uri'];
            $route = $uri->path();
            
            if ($route === '/social-action') {
                $this->handleSocialAction();
                return;
            }
            
            if ($route === '/send-message') {
                $this->handleSendMessage(); // From SocialMessageTrait
                return;
            }
        }
        
        if ($this->isAdmin()) {
            $this->enable([
                'onFlexAfterSave' => ['onFlexAfterSave', 0],
                'onFlexObjectBeforeSave' => ['onFlexObjectBeforeSave', 0],
            ]);
            return;
        }

        $this->enable([
            'onFlexAfterSave' => ['onFlexAfterSave', 0],
            'onFlexObjectBeforeSave' => ['onFlexObjectBeforeSave', 0],
            'onTwigInitialized' => ['onTwigInitialized', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
        ]);
    }

    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    public function onTwigSiteVariables()
    {
        $twig = $this->grav['twig'];
        $locator = $this->grav['locator'];
        
        $this->grav['assets']->addJs('plugin://social-core/assets/js/address-autocomplete.js', ['group' => 'bottom', 'defer' => true]);
        $this->grav['assets']->addCss('plugin://social-core/assets/css/address-autocomplete.css');
        $this->grav['assets']->addCss('plugin://social-core/assets/css/room-address.css');

        $rooms = [];
        $messages = [];
        $activities = [];
        $error = null;
        
        try {
            $roomsFile = $locator->findResource('user://data/flex-objects/rooms.json', true);
            if ($roomsFile && file_exists($roomsFile)) {
                $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
            }
            
            $messagesFile = $locator->findResource('user://data/flex-objects/messages.json', true);
            if ($messagesFile && file_exists($messagesFile)) {
                $messages = json_decode(file_get_contents($messagesFile), true) ?: [];
            }
            
            $activitiesFile = $locator->findResource('user://data/flex-objects/activity.json', true);
            if ($activitiesFile && file_exists($activitiesFile)) {
                $activities = json_decode(file_get_contents($activitiesFile), true) ?: [];
            }
            
            $membershipRequests = $this->loadMembershipRequests(); // From SocialVoteTrait
            
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $this->grav['log']->error('SocialCorePlugin: ' . $error);
        }
        
        $twig->twig_vars['rooms'] = $rooms;
        $twig->twig_vars['rooms_count'] = count($rooms);
        $twig->twig_vars['rooms_dir'] = true; 
        $twig->twig_vars['membership_requests'] = $membershipRequests ?? [];
        $twig->twig_vars['messages'] = $messages;
        $twig->twig_vars['messages_count'] = count($messages);
        $twig->twig_vars['activities'] = $activities;
        $twig->twig_vars['flex_loaded'] = true;
    }

    public function onTwigInitialized()
    {
        $this->grav['twig']->twig()->addFunction(
            new \Twig\TwigFunction('get_address_hierarchy', [$this, 'getAddressHierarchy']) // From SocialTagTrait
        );
    }

    /**
     * Handle social actions router
     */
    protected function handleSocialAction()
    {
        $user = $this->grav['user'];
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        
        if (!$user || !$user->authenticated || !$user->username) {
            $this->grav['messages']->add('Vous devez être connecté', 'error');
            $this->grav->redirect('/login');
            return;
        }

        $action = $_POST['action'] ?? '';
        $target = $_POST['target'] ?? '';
        $nonce = $_POST['social-nonce'] ?? '';

        if (!Utils::verifyNonce($nonce, 'social-action')) {
            $this->grav['messages']->add('Session expirée, veuillez réessayer', 'error');
            $this->grav->redirect($referer);
            return;
        }

        if (empty($action) || empty($target)) {
            $this->grav['messages']->add('Action invalide', 'error');
            $this->grav->redirect($referer);
            return;
        }

        try {
            switch ($action) {
                case 'follow':
                    $this->followUser($user->username, $target); // UserTrait
                    $this->logActivity('follow', 'user', $target); // UtilsTrait
                    $this->grav['messages']->add('Vous suivez maintenant cet utilisateur', 'success');
                    break;
                case 'unfollow':
                    $this->unfollowUser($user->username, $target); // UserTrait
                    $this->grav['messages']->add('Vous ne suivez plus cet utilisateur', 'info');
                    break;
                case 'join-space':
                    $this->joinSpace($user->username, $target); // SpaceTrait
                    $this->logActivity('join', 'space', $target); 
                    $this->grav['messages']->add('Vous avez rejoint la room !', 'success');
                    break;
                case 'leave-space':
                    $this->leaveSpace($user->username, $target); // SpaceTrait
                    break;
                case 'request-join':
                    $this->requestJoinSpace($user->username, $target); // VoteTrait
                    break;
                case 'cancel-request':
                    $this->cancelJoinRequest($user->username, $target); // VoteTrait
                    break;
                case 'vote-accept':
                    $this->voteOnRequest($user->username, $target, 'accept'); // VoteTrait
                    break;
                case 'vote-reject':
                    $this->voteOnRequest($user->username, $target, 'reject'); // VoteTrait
                    break;
                default:
                    $this->grav['messages']->add('Action inconnue: ' . $action, 'error');
            }
        } catch (\Exception $e) {
            $this->grav['log']->error('Social action error: ' . $e->getMessage());
            $this->grav['messages']->add('Erreur: ' . $e->getMessage(), 'error');
        }

        $this->grav->redirect($referer);
    }

    public function onFlexAfterSave(Event $event)
    {
        $this->handleFlexAfterSave($event); // SpaceTrait
    }
    
    public function onFlexObjectBeforeSave(Event $event)
    {
        $object = $event['object'];
        $type = $object->getFlexType();
        
        if ($type === 'social-spaces') {
             $this->handleSpaceBeforeSave($object); // SpaceTrait
        }

        if ($type === 'knowledge-tags') {
            $this->handleTagBeforeSave($object); // TagTrait
        }
    }

    public function onFormProcessed(Event $event)
    {
        $form = $event['form'];
        $action = $event['action'] ?? '';

        if ($action === 'call') return; 

        if ($form->name === 'create_space_form') {
            $this->handleSpaceCreation($form); // SpaceTrait
        }

        if ($form->name === 'create-room') {
            $this->handleRoomCreationFromForm($form); // SpaceTrait
        }
    }
}
