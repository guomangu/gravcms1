<?php
namespace Grav\Plugin;

/**
 * Trait SocialSpaceTrait
 * Gère les espaces/rooms (création, adhésion, synchro)
 */
trait SocialSpaceTrait
{
    /**
     * Flag to prevent recursive saves
     */
    protected static $isSaving = false;

    /**
     * Join a space
     */
    protected function joinSpace($username, $spaceSlug)
    {
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
        
        $members = $rooms[$spaceSlug]['members'] ?? [];
        $admins = $rooms[$spaceSlug]['admins'] ?? [];
        
        if (in_array($username, $members) || in_array($username, $admins)) {
            $this->grav['messages']->add('Vous êtes déjà membre de cette room', 'warning');
            return;
        }
        
        $members[] = $username;
        $rooms[$spaceSlug]['members'] = $members;
        
        file_put_contents($roomsFile, json_encode($rooms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->grav['log']->info("{$username} joined room {$spaceSlug}");
    }

    /**
     * Leave a space
     */
    protected function leaveSpace($username, $spaceSlug)
    {
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
        $admins = $room['admins'] ?? [];
        if (in_array($username, $admins)) {
            $this->grav['messages']->add('Les administrateurs ne peuvent pas quitter leur room', 'warning');
            return;
        }
        
        $members = $room['members'] ?? [];
        $members = array_values(array_filter($members, fn($m) => $m !== $username));
        $rooms[$spaceSlug]['members'] = $members;
        
        file_put_contents($roomsFile, json_encode($rooms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->grav['messages']->add('Vous avez quitté la room', 'info');
        $this->grav['log']->info("{$username} left room {$spaceSlug}");
    }

    /**
     * Handle Flex Object save events (Delegated from onFlexAfterSave)
     */
    public function handleFlexAfterSave($event)
    {
        if (self::$isSaving) return;

        $object = $event['object'];
        $type = $object->getFlexType();

        if ($type === 'social-spaces') {
             if ($this->isAdmin()) {
                 $this->syncSpaceMembers($object);
             }
         }
    }

    /**
     * Logic for onFlexObjectBeforeSave (Spaces)
     */
    public function handleSpaceBeforeSave($object, $form = null)
    {
        // Check if address data was submitted
        if (!$form) $form = $this->grav['request']->getParsedBody();
        
        $addressDataJson = $form['data']['address_data'] ?? null;
        
        if ($addressDataJson) {
            $addressData = json_decode($addressDataJson, true);
            if ($addressData && isset($addressData['properties'])) {
                $coordinates = $addressData['geometry']['coordinates'] ?? null;
                
                // Process hierarchy with coordinates (Uses SocialTagTrait)
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
     * Instance method for room creation
     */
    public function processCreateRoom($form)
    {
        $grav = $this->grav;
        $user = $grav['user'];
        
        if (!$user || !$user->authenticated || !$user->username) {
            $grav['messages']->add('Vous devez être connecté pour créer une room', 'error');
            $grav->redirect('/login?redirect=/create-room');
            return;
        }

        $data = $form->value()->toArray();
        $name = trim($data['name'] ?? '');
        
        if (empty($name) || strlen($name) < 3) {
            $grav['messages']->add('Le nom de la room doit contenir au moins 3 caractères', 'error');
            return;
        }

        $slug = $this->slugify($name);
        
        if (empty($slug) || $slug === '{KEY}' || strpos($slug, '{') !== false) {
            $slug = 'room-' . substr(uniqid(), -8);
        }
        
        $description = trim($data['description'] ?? '');
        $accessLevel = $data['access_level'] ?? 'public';
        $location = trim($data['location'] ?? '');

        try {
            $jsonFile = $grav['locator']->findResource('user://data/flex-objects/rooms.json', true, true);
            
            if (!$jsonFile) {
                $flexFolder = $grav['locator']->findResource('user://data/flex-objects', true, true);
                if (!$flexFolder) {
                    throw new \RuntimeException('Dossier flex-objects introuvable');
                }
                $jsonFile = $flexFolder . '/rooms.json';
            }
            
            $rooms = [];
            if (file_exists($jsonFile)) {
                $content = file_get_contents($jsonFile);
                $rooms = json_decode($content, true) ?: [];
            }
            
            if (isset($rooms[$slug])) {
                $slug = $slug . '-' . substr(uniqid(), -6);
            }

            // Process Address Data
            $addressTagId = null;
            $locationData = $data['address_data'] ?? null;
            
            if ($locationData) {
                $addressProps = json_decode($locationData, true);
                if ($addressProps && isset($addressProps['properties'])) {
                    $addressTagId = $this->processAddressHierarchy($addressProps['properties']);
                    $location = $addressProps['properties']['label'] ?? $location;
                }
            }

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

            $rooms[$slug] = $roomData;
            
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
     * Handle room creation from Grav form
     */
    protected function handleRoomCreationFromForm($form)
    {
        $this->processCreateRoom($form);
    }

    /**
     * Create a new space from Admin form
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

        $this->logActivity('create', 'space', $slug);
        $this->grav->redirect('/room/' . $slug);
    }

    /**
     * Get list of active spaces (Static)
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
}
