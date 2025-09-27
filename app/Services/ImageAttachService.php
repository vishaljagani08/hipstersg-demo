<?php
namespace App\Services;

use App\Models\Product;
use App\Models\Image;
use Illuminate\Support\Facades\DB;

class ImageAttachService
{
    /**
     * Attach upload's original image as product primary image; idempotent.
     */
    public static function attachUploadAsPrimary(Product $product, int $uploadId)
    {
        return DB::transaction(function() use($product,$uploadId) {
            // lock product row
            $p = Product::where('id',$product->id)->lockForUpdate()->first();

            // check existing
            $existing = Image::where('product_id',$p->id)->where('upload_id',$uploadId)->first();
            if ($existing) return $existing; // no-op

            // find original image record for upload
            $orig = Image::where('upload_id',$uploadId)->where('variant','original')->first();
            if (! $orig) throw new \Exception('Original image not yet available');

            // unset previous primary
            Image::where('product_id',$p->id)->update(['is_primary' => false]);

            // attach
            $orig->update(['product_id' => $p->id, 'is_primary' => true]);
            $p->update(['primary_image_id' => $orig->id]);

            return $orig;
        });
    }
}
