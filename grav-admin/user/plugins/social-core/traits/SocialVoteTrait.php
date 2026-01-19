<?php
namespace Grav\Plugin;

/**
 * Trait SocialVoteTrait
 * Gère le système de vote pour l'adhésion aux rooms
 */
trait SocialVoteTrait
{
    protected function loadMembershipRequests()
    {
        $file = $this->grav['locator']->findResource('user://data/flex-objects/membership-requests.json', true, true);
        if ($file && file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: [];
        }
        return [];
    }

    protected function saveMembershipRequests($requests)
    {
        $file = $this->grav['locator']->findResource('user://data/flex-objects/membership-requests.json', true, true);
        file_put_contents($file, json_encode($requests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    protected function requestJoinSpace($username, $spaceSlug)
    {
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
        
        $members = $room['members'] ?? [];
        $admins = $room['admins'] ?? [];
        if (in_array($username, $members) || in_array($username, $admins)) {
            $this->grav['messages']->add('Vous êtes déjà membre de cette room', 'warning');
            return;
        }
        
        $requests = $this->loadMembershipRequests();
        $requestKey = "{$spaceSlug}_{$username}";
        if (isset($requests[$requestKey])) {
            $this->grav['messages']->add('Vous avez déjà une demande en cours pour cette room', 'warning');
            return;
        }
        
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
        $this->logActivity('request_join', 'space', $spaceSlug);
    }

    protected function cancelJoinRequest($username, $spaceSlug)
    {
        $requests = $this->loadMembershipRequests();
        $requestKey = "{$spaceSlug}_{$username}";
        
        if (!isset($requests[$requestKey])) return;
        
        if ($requests[$requestKey]['username'] !== $username) return;
        
        unset($requests[$requestKey]);
        $this->saveMembershipRequests($requests);
        $this->grav['messages']->add('Demande annulée', 'info');
    }

    protected function voteOnRequest($voterUsername, $requestKey, $vote)
    {
        $requests = $this->loadMembershipRequests();
        
        if (!isset($requests[$requestKey])) {
            $this->grav['messages']->add('Demande introuvable', 'error');
            return;
        }
        
        $request = $requests[$requestKey];
        $spaceSlug = $request['space_slug'];
        
        $roomsFile = $this->grav['locator']->findResource('user://data/flex-objects/rooms.json', true);
        $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
        $room = $rooms[$spaceSlug] ?? null;
        
        if (!$room) return;
        
        $members = $room['members'] ?? [];
        $admins = $room['admins'] ?? [];
        $allMembers = array_unique(array_merge($members, $admins));
        
        if (!in_array($voterUsername, $allMembers)) {
            $this->grav['messages']->add('Seuls les membres peuvent voter', 'error');
            return;
        }
        
        if ($request['username'] === $voterUsername) {
            $this->grav['messages']->add('Vous ne pouvez pas voter pour votre propre demande', 'error');
            return;
        }
        
        $requests[$requestKey]['votes_accept'] = array_values(array_filter(
            $request['votes_accept'] ?? [], 
            fn($v) => $v !== $voterUsername
        ));
        $requests[$requestKey]['votes_reject'] = array_values(array_filter(
            $request['votes_reject'] ?? [], 
            fn($v) => $v !== $voterUsername
        ));
        
        if ($vote === 'accept') {
            $requests[$requestKey]['votes_accept'][] = $voterUsername;
        } else {
            $requests[$requestKey]['votes_reject'][] = $voterUsername;
        }
        
        $this->saveMembershipRequests($requests);
        $this->checkVoteMajority($requestKey, $requests[$requestKey], $allMembers);
        $this->grav['messages']->add('Vote enregistré', 'success');
    }

    protected function checkVoteMajority($requestKey, $request, $allMembers)
    {
        $votesAccept = count($request['votes_accept'] ?? []);
        $votesReject = count($request['votes_reject'] ?? []);
        $votingMembers = count($allMembers); // Exclure demandeur s'il etait membre? Non il l'est pas.
        
        $majority = ceil($votingMembers / 2);
        
        if ($votesAccept >= $majority) {
            $this->acceptMembershipRequest($request);
            return;
        }
        
        if ($votesReject >= $majority) {
            $this->rejectMembershipRequest($requestKey);
            return;
        }
        
        if (($votesAccept + $votesReject) >= $votingMembers) {
            $this->rejectMembershipRequest($requestKey);
        }
    }

    protected function acceptMembershipRequest($request)
    {
        $username = $request['username'];
        $spaceSlug = $request['space_slug'];
        
        $roomsFile = $this->grav['locator']->findResource('user://data/flex-objects/rooms.json', true, true);
        if ($roomsFile && file_exists($roomsFile)) {
            $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
            
            if (isset($rooms[$spaceSlug])) {
                $members = $rooms[$spaceSlug]['members'] ?? [];
                if (!in_array($username, $members)) {
                    $members[] = $username;
                    $rooms[$spaceSlug]['members'] = $members;
                    file_put_contents($roomsFile, json_encode($rooms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
        }
        
        $requests = $this->loadMembershipRequests();
        $requestKey = "{$spaceSlug}_{$username}";
        unset($requests[$requestKey]);
        $this->saveMembershipRequests($requests);
        
        $this->logActivity('accepted_join', 'space', $spaceSlug, $username);
        $this->grav['messages']->add("{$username} a été accepté comme membre !", 'success');
    }

    protected function rejectMembershipRequest($requestKey)
    {
        $requests = $this->loadMembershipRequests();
        if (!isset($requests[$requestKey])) return;
        
        $requests[$requestKey]['status'] = 'rejected';
        $requests[$requestKey]['resolved_at'] = date('Y-m-d H:i:s');
        $this->saveMembershipRequests($requests);
        
        $this->grav['log']->info("Membership request rejected: {$requests[$requestKey]['username']}");
    }
}
