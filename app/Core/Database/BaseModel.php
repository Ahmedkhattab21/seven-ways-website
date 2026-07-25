<?php

namespace App\Core\Database;

use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use Concerns\HasPublicUuid;
}
