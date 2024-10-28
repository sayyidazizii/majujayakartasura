<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Stock extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'item_name',
        'company_id',
        'warehouse_id',
        'item_id',
        'item_unit_id',
        'item_category_id',
        'last_balance',
        'created_id',
        'updated_id',
        'deleted_id',
        'data_state',
    ];

}
