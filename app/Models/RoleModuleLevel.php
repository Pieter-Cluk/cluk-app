<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['role_id', 'module', 'level'])]
class RoleModuleLevel extends Model
{
    public $timestamps = false;
}
