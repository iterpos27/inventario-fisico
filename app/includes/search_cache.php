<?php

declare(strict_types=1);

const SEARCH_CACHE_TTL = 600;

function search_cache_dir(): string
{
    return STORAGE_PATH . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'search';
}

function search_cache_key(string $namespace, array $parts): string
{
    return sha1($namespace . '|' . json_encode($parts, JSON_UNESCAPED_UNICODE));
}

function search_cache_get(string $key): ?array
{
    $file = search_cache_dir() . DIRECTORY_SEPARATOR . $key . '.json';
    if (!is_file($file) || filemtime($file) < time() - SEARCH_CACHE_TTL) {
        return null;
    }

    $payload = json_decode((string) file_get_contents($file), true);
    return is_array($payload) ? $payload : null;
}

function search_cache_set(string $key, array $value): void
{
    $dir = search_cache_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    file_put_contents($dir . DIRECTORY_SEPARATOR . $key . '.json', json_encode($value, JSON_UNESCAPED_UNICODE));
}

function search_cache_invalidate(): void
{
    $dir = search_cache_dir();
    if (!is_dir($dir)) {
        return;
    }

    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
