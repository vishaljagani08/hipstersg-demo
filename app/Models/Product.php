<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'sku', 'description', 'price', 'primary_image_id'];
    public function images()
    {
        return $this->hasMany(Image::class);
    }
}
