<?php

declare (strict_types = 1);

namespace TestMate\Services\Agama;

use Illuminate\Support\Facades\Log;
use TestMate\Cache\CacheHandler;
use TestMate\Repositories\Agama\AgamaRepository;
use TestMate\Services\AbstractService;

/**
 * Agama service
 * @author TestMate <dev@testmate.org>
 */
final class AgamaService extends AbstractService
{
    /**
     * Dependenc injection
     */
    public function __construct(CacheHandler $cache, AgamaRepository $repository)
    {
        $this->cache = $cache;
        $this->repository = $repository;
    }

    /**
     * Get all data source
     * @return iterable
     */
    public function fetchAll(): ?iterable
    {
        // First we want to check is there existence cache
        $key = sprintf('%s:%s:%s', get_class($this->repository), 'agamas', 'all-data');
        if ($this->cache->isCached($key)) {
            $data = $this->cache->getItem($key);
        } else {
            // retreive data from repository
            $fetch = $this->repository->fetchAll();
            // check if there any error message
            if ($fetch->getErrors()) {
                if (config('testmate.allow_loggin')) {
                    Log::emergency($fetch->getErrors());
                }
                return null;
            }

            $data = $fetch->getEntities();

            // cache data result
            $this->cache->cache($key, $data);
        }
        return $data;
    }
}
