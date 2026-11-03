<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeArticle extends Model
{
    protected $fillable = ['title', 'body', 'source_name', 'source_url', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
