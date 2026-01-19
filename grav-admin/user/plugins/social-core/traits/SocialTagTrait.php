<?php
namespace Grav\Plugin;

/**
 * Trait SocialTagTrait
 * Gère les tags et l'adresse (Geocoding, Hiérarchie)
 */
trait SocialTagTrait
{
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
            
            array_unshift($hierarchy, $tag); 
            
            $parentId = $tag->getProperty('parent');
            if (!$parentId) break;
            
            $currentId = $parentId;
        }
        
        return $hierarchy;
    }

    /**
     * Process address hierarchy and return the leaf node ID (Number or Street)
     */
    public function processAddressHierarchy($props, $coordinates = null)
    {
        $directory = $this->getFlexDirectory('knowledge-tags');
        if (!$directory) return null;

        // 0. Country
        $countryName = 'France';
        $countryTag = $this->findOrCreateTag($directory, $countryName, 'pays', null, [
            'description' => 'Pays'
        ]);

        // 1. Region
        $regionName = $props['context'] ?? substr($props['citycode'] ?? '00000', 0, 2); 
        $regionTag = $this->findOrCreateTag($directory, $regionName, 'region', $countryTag->getKey(), [
            'description' => 'Région / Département auto-généré'
        ]);

        // 2. City
        $cityName = $props['city'] ?? 'Ville Inconnue';
        $cityTag = $this->findOrCreateTag($directory, $cityName, 'ville', $regionTag->getKey(), [
            'citycode' => $props['citycode'] ?? '',
            'postcode' => $props['postcode'] ?? '',
            'description' => $props['city'] ?? ''
        ]);

        // 3. Street
        $streetName = $props['street'] ?? $props['name'] ?? 'Rue Inconnue';
        $streetTag = $this->findOrCreateTag($directory, $streetName, 'rue', $cityTag->getKey(), [
            'description' => $streetName
        ]);

        // 4. Number
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
     */
    protected function findOrCreateTag($directory, $name, $type, $parentId = null, $extraData = [])
    {
        if (empty($name)) return null;

        $parentHash = $parentId ? substr(md5($parentId), 0, 5) : 'root';
        $slugBase = self::staticSlugify($name);
        
        $uniqueSlug = $parentId ? "{$type}-{$slugBase}-{$parentHash}" : self::staticSlugify("{$type}-{$name}");
        
        $object = $directory->getObject($uniqueSlug);
        
        if ($object) {
            return $object;
        }

        try {
            $data = array_merge([
                'name' => $name,
                'slug' => $uniqueSlug,
                'tag_type' => $type,
                'parent' => $parentId,
                'published' => true
            ], $extraData);

            $object = $directory->createObject($data, $uniqueSlug);
            $object->save();
            
            return $object;
        } catch (\Exception $e) {
            $this->grav['log']->warning("Tag creation collision handled for: $uniqueSlug");
            return $directory->getObject($uniqueSlug);
        }
    }

    /**
     * Logic for onFlexObjectBeforeSave (Knowledge Tags)
     */
    public function handleTagBeforeSave($object)
    {
        $lat = $object->getProperty('latitude');
        $lon = $object->getProperty('longitude');
        
        if ((empty($lat) || empty($lon))) {
            $name = $object->getProperty('name');
            $tagType = $object->getProperty('tag_type');
            
            if ($name && in_array($tagType, ['ville', 'rue', 'numero'])) {
                $query = $name;
                $parent = $object->getProperty('parent');
                if ($parent) {
                    $directory = $this->getFlexDirectory('knowledge-tags');
                    $parentObj = $directory ? $directory->getObject($parent) : null;
                    if ($parentObj) {
                        $query .= ' ' . $parentObj->getProperty('name');
                    }
                }
                
                $url = "https://api-adresse.data.gouv.fr/search/?q=" . urlencode($query) . "&limit=1";
                try {
                    $response = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 2]]));
                    if ($response) {
                        $json = json_decode($response, true);
                        if (!empty($json['features'])) {
                            $feat = $json['features'][0];
                            $coords = $feat['geometry']['coordinates'] ?? null;
                            if ($coords) {
                                $object->setProperty('longitude', $coords[0]);
                                $object->setProperty('latitude', $coords[1]);
                                
                                if (!$object->getProperty('postcode')) $object->setProperty('postcode', $feat['properties']['postcode'] ?? '');
                                if (!$object->getProperty('citycode')) $object->setProperty('citycode', $feat['properties']['citycode'] ?? '');
                                
                                $this->grav['log']->info("Auto-geocoded tag: {$name}");
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->grav['log']->warning("Geocoding failed for {$name}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Static method options (Moved from main class)
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
}
