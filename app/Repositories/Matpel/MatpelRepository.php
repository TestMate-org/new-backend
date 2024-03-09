<?php declare (strict_types = 1);

namespace TestMate\Repositories\Matpel;

use Illuminate\Support\Facades\DB;
use TestMate\Repositories\AbstractRepository;
use TestMate\Utils\Error;

/**
 * MatpelRepository repository
 * @author TestMate <dev@testmate.org>
 */
final class MatpelRepository extends AbstractRepository
{
    /**
     * Table name of repository
     * @var string
     */
    protected string $table = 'matpels';

    /**
     * Get table repository name
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Get all data source
     * @return self
     */
    public function fetchAll(): self
    {
        try {
            $data = DB::table($this->table)
                ->get();

            $this->entities = $data;
        } catch (\Exception $e) {
            array_push($this->errors, Error::get($e));
        }

        return $this;
    }

    /**
     * Delete multi data source
     * @param array $ids
     * @return self
     */
    public function deletes(array $ids): self
    {
        try {
            DB::table($this->table)
                ->whereIn($this->primary_key, $ids)
                ->delete();
        } catch (\Exception $e) {
            array_push($this->errors, Error::get($e));
        }

        return $this;
    }
}
