<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cohort extends Model
{
    protected $table        = 'cohorts';
    protected $fillable     = ['school_id', 'name', 'description', 'start_date', 'end_date'];
    
    /**
     * Get the users (students) that belong to this cohort
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_cohorts')
                    ->withTimestamps();
    }
}
