<?php

namespace App;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

class Peserta extends Model
{
    use Uuids;

    /**
     * protected unviewable property
     * @var array
     */
    protected $guarded = [];

    /**
     * Peserta's agama
     * @return object
     * @author TestMate <dev@testmate.org>
     */
    public function agama()
    {
        return $this->belongsTo(Agama::class);
    }

    /**
     * Peserta's jurusan
     * @return object
     * @author TestMate <dev@testmate.org>
     */
    public function jurusan()
    {
        return $this->belongsTo(Jurusan::class);
    }

    /**
     * Group member
     * @return object
     * @author TestMate <dev@testmate.org>
     */
    public function group()
    {
        return $this->belongsTo(GroupMember::class, 'id', 'student_id');
    }
}
