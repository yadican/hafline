<?php

namespace App\Repositories;

use App\Models\Example;

class ExampleRepository extends Repository
{
    public function __construct(Example $model)
    {
        parent::__construct($model);
    }
}
