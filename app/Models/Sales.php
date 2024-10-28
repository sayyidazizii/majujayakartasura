<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sales extends Model
{
    use SoftDeletes, HasFactory;
    protected $fillable = [
        'sales_invoice_date',
        'subtotal_amount',
        'subtotal_item',
        'paid_amount',
        'total_amount',
        'change_amount',
        'payment_method',
        'company_id',
        'created_id',
        'updated_id',
        'deleted_id',
        'data_state',
    ];

    public function items()
    {
        return $this->hasMany(SalesItem::class, 'sales_invoice_id');
    }
}
