<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = ['upload_id', 'variant', 'path', 'width', 'height', 'product_id', 'is_primary'];
    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
