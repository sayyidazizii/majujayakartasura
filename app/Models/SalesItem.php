<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesItem extends Model
{
    use SoftDeletes, HasFactory;
    protected $fillable = [
        'sales_invoice_id',
        'item_id',
        'item_unit_id',
        'item_category_id',
        'last_balance',
        'quantity',
        'item_unit_price',
        'subtotal_amount',
        'subtotal_amount_after_discount',
        'discount_percentage',
        'discount_amount',
        'company_id',
        'created_id',
        'updated_id',
        'deleted_id',
        'data_state',
    ];

    public function sales()
    {
        return $this->belongsTo(Sales::class);
    }
}
