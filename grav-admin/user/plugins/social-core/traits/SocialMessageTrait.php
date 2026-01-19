<?php
namespace Grav\Plugin;

use Grav\Common\Utils;

/**
 * Trait SocialMessageTrait
 * Gère les fonctionnalités liées aux messages
 */
trait SocialMessageTrait
{
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
}
