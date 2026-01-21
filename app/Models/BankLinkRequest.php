<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BankLinkRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'token',
        'merchant_email',
        'merchant_name',
        'business_name',
        'status',
        'sent_at',
        'opened_at',
        'completed_at',
        'expires_at',
        'plaid_item_id',
        'institution_name',
        'accounts_connected',
        'notes',
        'sent_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'accounts_connected' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($request) {
            if (empty($request->token)) {
                $request->token = Str::random(64);
            }
            if (empty($request->expires_at)) {
                $request->expires_at = now()->addDays(7); // Default 7 days expiry
            }
        });
    }

    // Relationships
    public function application(): BelongsTo
    {
        return $this->belongsTo(MCAApplication::class, 'application_id');
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    // Accessors
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at->isPast();
    }

    public function getIsValidAttribute(): bool
    {
        return ! $this->is_expired && in_array($this->status, ['pending', 'sent', 'opened']);
    }

    public function getLinkUrlAttribute(): string
    {
        return route('merchant.bank.connect', ['token' => $this->token]);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'secondary',
            'sent' => 'info',
            'opened' => 'warning',
            'completed' => 'success',
            'expired' => 'secondary',
            'failed' => 'danger',
            default => 'secondary',
        };
    }

    // Methods
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsOpened(): void
    {
        if ($this->status === 'sent') {
            $this->update([
                'status' => 'opened',
                'opened_at' => now(),
            ]);
        }
    }

    public function markAsCompleted(string $plaidItemId, string $institutionName, array $accounts): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'plaid_item_id' => $plaidItemId,
            'institution_name' => $institutionName,
            'accounts_connected' => $accounts,
        ]);
    }

    public function markAsFailed(?string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'notes' => $reason,
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'sent', 'opened'])
            ->where('expires_at', '>', now());
    }

    public function scopeForApplication($query, $applicationId)
    {
        return $query->where('application_id', $applicationId);
    }
}
