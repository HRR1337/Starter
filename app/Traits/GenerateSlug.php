<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

trait GenerateSlug
{
    protected function generateSlug($name, $tableName)
    {
        return substr(md5(uniqid(auth()->id(), true)), 0, 8);
    }
}
