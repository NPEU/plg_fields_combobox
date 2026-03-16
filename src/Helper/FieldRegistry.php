<?php
declare(strict_types=1);

namespace NPEU\Plugin\Fields\ComboBox\Helper;

\defined('_JEXEC') or die;

/**
 * Lightweight in-memory registry for field ids.
 *
 * Keying: "context|name" => id
 */
final class FieldRegistry
{
    /** @var array<string,int> */
    private static array $map = [];

    private function __construct()
    {
        // static helper only
    }

    public static function set(string $context, string $name, int $id): void
    {
        $key = self::key($context, $name);
        self::$map[$key] = $id;
    }

    public static function get(string $context, string $name): ?int
    {
        $key = self::key($context, $name);
        if (isset(self::$map[$key])) {
            return (int) self::$map[$key];
        }

        // Try fallback: name-only key (no context)
        $key2 = self::key('', $name);
        if (isset(self::$map[$key2])) {
            return (int) self::$map[$key2];
        }

        return null;
    }

    private static function key(string $context, string $name): string
    {
        $ctx = $context !== '' ? $context : '(noctx)';
        return $ctx . '|' . strtolower($name);
    }
}