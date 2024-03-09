<?php

declare (strict_types = 1);

namespace TestMate\Cache;

/**
 * Cache handler interface
 * @author TestMate
 */
interface CacheHandlerInterface
{
    public function cache(string $name, string $key, $data, int $seconds = 5): void;

    public function isCached(string $name, string $key): bool;

    public function getItem(string $name, string $key);

    public function deleteItem(string $name, string $key);
}
