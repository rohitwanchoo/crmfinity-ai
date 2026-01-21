<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaidTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'plaid_account_id',
        'plaid_transaction_id',
        'transaction_date',
        'description',
        'amount',
        'type',
        'category',
        'subcategory',
        'merchant_name',
        'pending',
        'iso_currency_code',
        'payment_channel',
        'location',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'pending' => 'boolean',
        'location' => 'array',
    ];

    public function plaidAccount(): BelongsTo
    {
        return $this->belongsTo(PlaidAccount::class);
    }

    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }

    public function getFormattedAmountAttribute(): string
    {
        $prefix = $this->type === 'credit' ? '+' : '-';

        return $prefix.'$'.number_format($this->amount, 2);
    }

    public function getCategoryIconAttribute(): string
    {
        return match (strtolower($this->category ?? '')) {
            'food_and_drink', 'food and drink' => 'restaurant',
            'travel' => 'airplane',
            'transportation' => 'car',
            'shopping', 'shops' => 'shopping-bag',
            'entertainment', 'recreation' => 'film',
            'healthcare', 'health and fitness' => 'heart',
            'utilities', 'service' => 'lightning-bolt',
            'transfer' => 'switch-horizontal',
            'payment' => 'credit-card',
            'income' => 'cash',
            default => 'currency-dollar',
        };
    }

    public function scopeCredits($query)
    {
        return $query->where('type', 'credit');
    }

    public function scopeDebits($query)
    {
        return $query->where('type', 'debit');
    }

    public function scopeNotPending($query)
    {
        return $query->where('pending', false);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }
}
