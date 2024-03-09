<?php declare (strict_types = 1);

namespace TestMate\Repositories\Jurusan;

use Illuminate\Support\Facades\DB;
use TestMate\Repositories\AbstractRepository;
use TestMate\Utils\Error;

/**
 * JurusanRepository repository
 * @author TestMate <dev@testmate.org>
 */
final class JurusanRepository extends AbstractRepository
{
    /**
     * Table of repository
     * @var string
     */
    protected string $table = 'jurusans';

    protected bool $timestamps = false;

    /**
     * Get table
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Fetch all data
     * @return self
     */
    public function fetchAll(): self
    {
        try {
            $data = DB::table($this->table)
                ->orderBy('nama')
                ->get();

            $this->entities = $data;
        } catch (\Exception $e) {
            array_push($this->errors, Error::get($e));
        }
        return $this;
    }

    /**
     * Delete multiple data
     * @return self
     */
    public function deletes(array $ids): self
    {
        try {
            DB::table($this->table)
                ->whereIn('id', $ids)
                ->delete();

        } catch (\Exception $e) {
            array_push($this->errors, Error::get($e));
        }
        return $this;
    }
}
