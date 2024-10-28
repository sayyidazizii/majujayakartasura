<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'item_category_name',
        'item_category_code',
        'company_id',
        'created_id',
        'updated_id',
        'deleted_id',
        'data_state',
    ];
}
