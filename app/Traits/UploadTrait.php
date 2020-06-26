<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;

trait UploadTrait
{
    public function uploadOne(UploadedFile $uploadedFile,
                              $folder = null,
                              $disk = 'public', $filename = null, $prefix = "")
    {

        $name = !is_null($filename) ? $filename : $prefix . "" . time();

        $file = $uploadedFile->storeAs($folder, $name . '.' . $uploadedFile->getClientOriginalExtension(), $disk);

        return $file;
    }
}
