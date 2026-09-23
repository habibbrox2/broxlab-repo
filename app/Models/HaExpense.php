<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaExpense extends Model
{
    protected $table = 'ha_expenses';
    protected $guarded = [];
    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public const CATEGORIES = ['rent', 'utility', 'salary', 'transport', 'purchase', 'marketing', 'other'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
