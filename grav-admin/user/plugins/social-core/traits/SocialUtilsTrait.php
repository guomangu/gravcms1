<?php
namespace Grav\Plugin;

/**
 * Trait SocialUtilsTrait
 * Utilitaires communs (Logs, FlexDirectory, Slugs)
 */
trait SocialUtilsTrait
{
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
     * Create a URL-friendly slug from a string
     */
    protected function slugify($text)
    {
        return self::staticSlugify($text);
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
}
