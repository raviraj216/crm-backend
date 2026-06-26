<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    protected $fillable = [
        'name',
        'phone_number_id',
        'phone_number',
        'access_token',
        'contact',
        'category',
        'city',
        'address',
        'website',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'access_token',   // never leak in API responses
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsappConversation::class);
    }
}
