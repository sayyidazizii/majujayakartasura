<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'item_unit_name',
        'item_unit_code',
        'company_id',
        'created_id',
        'updated_id',
        'deleted_id',
        'data_state',
    ];
}
