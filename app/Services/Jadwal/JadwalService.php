<?php declare (strict_types = 1);

namespace TestMate\Services\Jadwal;

use App\Models\CacheConstant;
use App\Models\JadwalConstant;
use App\Models\UjianConstant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use TestMate\Cache\CacheHandler;
use TestMate\Services\AbstractService;

/**
 * Jadwal Service
 *
 * @since 1.0.0 <bakwan>
 * @author TestMate <dev@testmate.org>
 */
final class JadwalService extends AbstractService
{
    /**
     * Inject dependency
     *
     * @param CacheHandler $cache
     * @since 1.0.0 <bakwan>
     */
    public function __construct(CacheHandler $cache)
    {
        $this->cache = $cache;
    }

    /**
     * find single jawaban by id
     *
     * @param string $jadwal_id
     * @return Model|Builder|mixed|object|null
     * @since 1.0.0 <bakwan>
     */
    public function findJadwal(string $jadwal_id)
    {
        $query = DB::table('jadwals')
            ->where('id', $jadwal_id);
        if (config('testmate.enable_cache')) {
            $is_cached = $this->cache->isCached(CacheConstant::KEY_JADWAL, $jadwal_id);
            if ($is_cached) {
                $jadwal = $this->cache->getItem(CacheConstant::KEY_JADWAL, $jadwal_id);
            } else {
                $jadwal = $query->first();
                if ($jadwal) {
                    $this->cache->cache(CacheConstant::KEY_JADWAL, $jadwal_id, $jadwal);
                }
            }
        } else {
            $jadwal = $query->first();
        }
        return $jadwal;
    }

    /**
     * Get ujian active today
     *
     * @cacheable
     *
     * @return object
     * @since 1.0.0 <bakwan>
     */
    public function activeToday()
    {
        $query = DB::table('jadwals')
            ->where([
                'status_ujian' => JadwalConstant::STATUS_ACTIVE,
                'tanggal' => now()->format('Y-m-d'),
            ])
            ->select([
                'id',
                'alias',
                'banksoal_id',
                'lama',
                'mulai',
                'tanggal',
                'setting',
                'group_ids',
                'view_result',
            ]);

        if (config('testmate.enable_cache')) {
            $is_cached = $this->cache->isCached(CacheConstant::KEY_JADWAL_ACTIVE_TODAY, __METHOD__);
            if ($is_cached) {
                $jadwals = $this->cache->getItem(CacheConstant::KEY_JADWAL_ACTIVE_TODAY, __METHOD__);
            } else {
                $jadwals = $query->get();
                if ($jadwals) {
                    $this->cache->cache(CacheConstant::KEY_JADWAL_ACTIVE_TODAY, __METHOD__, $jadwals);
                }
            }
        } else {
            $jadwals = $query->get();
        }
        return $jadwals;
    }

    /**
     * Get ujian has finished by student
     *
     * @param string $student_id
     * @return object
     * @since 1.0.0 <bakwan>
     */
    public function hasCompletedBy(string $student_id)
    {
        $hascomplete = DB::table('siswa_ujians')->where([
            'peserta_id' => $student_id,
            'status_ujian' => UjianConstant::STATUS_FINISHED,
        ])->select(['jadwal_id'])->get()->pluck('jadwal_id');

        return $hascomplete;
    }
}
