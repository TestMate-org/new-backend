<?php declare (strict_types = 1);

namespace DummyNamespace;

use TestMate\Cache\CacheHandler;
use TestMate\Services\AbstractService;

/**
 * DummyClass service
 * @author TestMate <dev@testmate.org>
 */
final class DummyClass extends AbstractService
{
    /**
     * Dependency injection
     */
    public function __construct(CacheHandler $cache)
    {
        $this->cache = $cache;
    }
}
