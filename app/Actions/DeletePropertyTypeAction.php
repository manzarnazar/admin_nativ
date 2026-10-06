<?php

namespace App\Actions;

use App\Models\PropertyType;

class DeletePropertyTypeAction
{
    public function handle(PropertyType $propertyType): void
    {
        $propertyType->delete();
    }
}
