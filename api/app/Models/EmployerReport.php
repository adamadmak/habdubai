<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployerReport extends Model
{
    protected $fillable = ['member_id', 'employer_name', 'text', 'contactable', 'status', 'note', 'handled_by'];

    protected function casts(): array
    {
        return ['contactable' => 'boolean'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
