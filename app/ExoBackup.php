<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TestMateBackup extends Model
{
    public const TYPE_BACKUP = "BACKUP";
    public const TYPE_RESTORE = "RESTORE";
}
