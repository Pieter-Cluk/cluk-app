<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'module', 'level'])]
class UserModuleOverride extends Model
{
    public $timestamps = false;
}
