<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    protected $fillable = ['original_name', 'size', 'checksum', 'status', 'storage_path', 'meta', 'batch_key'];
    public function images()
    {
        return $this->hasMany(Image::class);
    }
}
