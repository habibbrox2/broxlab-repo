<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property float $amount
 * @property string $currency
 * @property string $method
 * @property string|null $transaction_id
 * @property string|null $payer_phone
 * @property string $status
 * @property string|null $admin_note
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class UserRecharge extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'method',
        'transaction_id',
        'payer_phone',
        'status',
        'admin_note',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'pending' => 'অপেক্ষা (Pending)',
            'processing' => 'প্রক্রিয়াকরণ চালু (Processing)',
            'completed' => 'সম্পন্ন (Completed)',
            'failed' => 'ব্যর্থ (Failed)',
            'cancelled' => 'বাতিল (Cancelled)',
            'expired' => 'মেয়াদ উত্তীর্ণ (Expired)',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function isPaid(): bool
    {
        return $this->status === 'completed';
    }
}
