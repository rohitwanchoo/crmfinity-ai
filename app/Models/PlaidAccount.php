<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaidAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'plaid_item_id',
        'plaid_account_id',
        'name',
        'official_name',
        'type',
        'subtype',
        'mask',
        'current_balance',
        'available_balance',
        'limit',
        'iso_currency_code',
        'is_active',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'available_balance' => 'decimal:2',
        'limit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function plaidItem(): BelongsTo
    {
        return $this->belongsTo(PlaidItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PlaidTransaction::class);
    }

    public function getDisplayNameAttribute(): string
    {
        $name = $this->official_name ?? $this->name;
        if ($this->mask) {
            return "{$name} (...{$this->mask})";
        }

        return $name;
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'depository' => 'bank',
            'credit' => 'credit-card',
            'loan' => 'document-text',
            'investment' => 'trending-up',
            default => 'cash',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'depository' => 'Bank Account',
            'credit' => 'Credit Card',
            'loan' => 'Loan',
            'investment' => 'Investment',
            default => ucfirst($this->type),
        };
    }
}
