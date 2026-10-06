<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeBase extends Model
{
    protected $fillable = [
        'url',
        'cluster',
        'topic',
        'content',
        'keywords',
        'description',
    ];

    protected $casts = [
        'keywords' => 'array',
    ];
}