<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class HubspotConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'hubspot_portal_id',
        'hubspot_user_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'scopes' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Get the user that owns the connection.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the synced offers for this connection.
     */
    public function syncedOffers(): HasMany
    {
        return $this->hasMany(HubspotSyncedOffer::class);
    }

    /**
     * Check if the access token is expired.
     */
    public function isTokenExpired(): bool
    {
        return $this->token_expires_at->isPast();
    }

    /**
     * Check if the token will expire soon (within 5 minutes).
     */
    public function tokenExpiresSoon(): bool
    {
        return $this->token_expires_at->subMinutes(5)->isPast();
    }

    /**
     * Get the decrypted access token.
     */
    public function getDecryptedAccessToken(): string
    {
        try {
            return Crypt::decryptString($this->access_token);
        } catch (\Exception $e) {
            return $this->access_token;
        }
    }

    /**
     * Get the decrypted refresh token.
     */
    public function getDecryptedRefreshToken(): string
    {
        try {
            return Crypt::decryptString($this->refresh_token);
        } catch (\Exception $e) {
            return $this->refresh_token;
        }
    }

    /**
     * Set the access token (encrypted).
     */
    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = Crypt::encryptString($value);
    }

    /**
     * Set the refresh token (encrypted).
     */
    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = Crypt::encryptString($value);
    }

    /**
     * Scope to get active connections.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get connections for a specific portal.
     */
    public function scopeForPortal($query, string $portalId)
    {
        return $query->where('hubspot_portal_id', $portalId);
    }
}
