<?php declare (strict_types = 1);

namespace TestMate\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TestMate\Cache\CacheHandler;
use TestMate\Repositories\RepositoryInterface;
use TestMate\Utils\Error;

/**
 * Pagination service
 * @author TestMate <dev@testmate.org>
 */
final class PaginationService
{
    /**
     * Cache handler
     * @var CacheHandler $cache
     */
    private $cache;

    /**
     * @param CacheHandler $cache
     * @return void
     */
    public function __construct(CacheHandler $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Create pagination data
     * @param RepositoryInterface $repository
     * @param array $conditions
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function build(RepositoryInterface $repository, array $conditions, int $perPage = 10): ?LengthAwarePaginator
    {
        try {
            $data = DB::table($repository->getTable());
            if ($conditions) {
                $data = $data->where($conditions);
            }
            $data = $data->paginate($perPage);
        } catch (\Exception $e) {
            if (config('testmate.log')) {
                Log::emergency([Error::get($e)]);
            }
            return null;
        }

        return $data;
    }
}
