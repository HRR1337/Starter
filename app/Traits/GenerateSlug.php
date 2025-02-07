<?php

namespace App\Traits;

trait GenerateSlug
{
    protected function generateSlug($name, $tableName)
    {
        return substr(md5(uniqid(auth()->id(), true)), 0, 8);
    }
}
