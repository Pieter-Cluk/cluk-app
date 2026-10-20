<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['role_id', 'right'])]
class RoleRight extends Model
{
    public $timestamps = false;
}
