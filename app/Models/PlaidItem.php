<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaidItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'application_id',
        'plaid_item_id',
        'access_token',
        'institution_id',
        'institution_name',
        'status',
        'error_code',
        'error_message',
        'transaction_cursor',
        'consent_expiration_time',
        'last_synced_at',
        'has_pending_sync',
        'auth_verified',
    ];

    protected $casts = [
        'consent_expiration_time' => 'datetime',
        'last_synced_at' => 'datetime',
        'has_pending_sync' => 'boolean',
        'auth_verified' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(MCAApplication::class, 'application_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(PlaidAccount::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function needsReauth(): bool
    {
        return in_array($this->status, ['login_required', 'error', 'pending_expiration']);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'login_required' => 'warning',
            'error' => 'danger',
            'pending_expiration' => 'warning',
            'revoked' => 'danger',
            default => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Connected',
            'login_required' => 'Re-login Required',
            'error' => 'Error',
            'pending_expiration' => 'Expiring Soon',
            'revoked' => 'Revoked',
            default => ucfirst($this->status),
        };
    }
}
