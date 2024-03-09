<?php declare (strict_types = 1);

namespace TestMate\Repositories\User;

use Illuminate\Support\Facades\DB;
use TestMate\Repositories\AbstractRepository;
use TestMate\Utils\Error;

/**
 * UserRepository repository
 * @author TestMate <dev@testmate.org>
 */
final class UserRepository extends AbstractRepository
{
    /**
     * Table of repository
     * @var string $table
     */
    protected string $table = 'users';

    /**
     * Get repository's table
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Delete multiple data
     * @param array $user_ids
     * @return self
     */
    public function deletes(array $user_ids): self
    {
        try {
            DB::table($this->table)
                ->whereIn($this->primary_key, $user_ids)
                ->delete();
        } catch (\Exception $e) {
            array_push($this->errors, Error::get($e));
        }
        return $this;
    }
}
