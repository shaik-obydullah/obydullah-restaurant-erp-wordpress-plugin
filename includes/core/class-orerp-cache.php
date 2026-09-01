<?php
/**
 * Object Cache Helper
 *
 * Wraps query reader results with wp_cache_get()/wp_cache_set() and provides
 * version-based invalidation so that any write to a given table immediately
 * invalidates all cached reads for that table (preventing stale data).
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Obydullah_ERP_Cache')) {
    class Obydullah_ERP_Cache
    {
        const GROUP = 'orerp_cache';

        const TTL = 300;

        /**
         * Get a cached value (or false when missing).
         *
         * @param string $key   Cache key (unique within the table group).
         * @param string $table Table name used as the cache version group.
         * @return mixed|false
         */
        public static function get($key, $table)
        {
            if (!is_string($table) || '' === $table) {
                return false;
            }

            $version = self::version($table);
            return wp_cache_get($version . ':' . md5($key), self::GROUP);
        }

        /**
         * Store a value in the cache.
         *
         * @param string $key     Cache key.
         * @param string $table   Table name (version group).
         * @param mixed  $value   Value to cache.
         * @param int    $expiration TTL in seconds.
         * @return bool
         */
        public static function set($key, $table, $value, $expiration = self::TTL)
        {
            if (!is_string($table) || '' === $table) {
                return false;
            }

            $version = self::version($table);
            return wp_cache_set($version . ':' . md5($key), $value, self::GROUP, $expiration);
        }

        /**
         * Invalidate all cached reads for a given table by bumping its version.
         *
         * @param string $table Table name.
         * @return void
         */
        public static function invalidate($table)
        {
            if (!is_string($table) || '' === $table) {
                return;
            }

            $version = self::version($table);
            update_option('orerp_cache_' . $table, $version + 1, false);
        }

        /**
         * Current version number for a table's cache group.
         *
         * @param string $table Table name.
         * @return int
         */
        private static function version($table)
        {
            $version = get_option('orerp_cache_' . $table, 1);
            return intval($version);
        }
    }
}
