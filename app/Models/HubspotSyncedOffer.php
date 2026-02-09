<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubspotSyncedOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'hubspot_connection_id',
        'mca_offer_id',
        'hubspot_deal_id',
        'hubspot_contact_id',
        'hubspot_company_id',
        'sync_status',
        'sync_error',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the HubSpot connection.
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(HubspotConnection::class, 'hubspot_connection_id');
    }

    /**
     * Get the MCA offer.
     */
    public function mcaOffer(): BelongsTo
    {
        return $this->belongsTo(McaOffer::class, 'mca_offer_id', 'offer_id');
    }

    /**
     * Scope to get synced offers.
     */
    public function scopeSynced($query)
    {
        return $query->where('sync_status', 'synced');
    }

    /**
     * Scope to get failed syncs.
     */
    public function scopeFailed($query)
    {
        return $query->where('sync_status', 'failed');
    }

    /**
     * Scope to get pending syncs.
     */
    public function scopePending($query)
    {
        return $query->where('sync_status', 'pending');
    }

    /**
     * Mark as synced.
     */
    public function markAsSynced(): void
    {
        $this->update([
            'sync_status' => 'synced',
            'sync_error' => null,
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'sync_status' => 'failed',
            'sync_error' => $error,
            'last_synced_at' => now(),
        ]);
    }
}
