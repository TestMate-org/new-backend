<?php declare (strict_types = 1);

namespace TestMate\Services\Banksoal;

use TestMate\Cache\CacheHandler;
use TestMate\Services\AbstractService;

/**
 * BanksoalService service
 * @author TestMate <dev@testmate.org>
 */
final class BanksoalService extends AbstractService
{
    /**
     * Dependency injection
     */
    public function __construct(CacheHandler $cache)
    {
        $this->cache = $cache;
    }
}
