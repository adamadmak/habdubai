<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotText extends Model
{
    protected $fillable = ['key', 'language', 'text'];
}
