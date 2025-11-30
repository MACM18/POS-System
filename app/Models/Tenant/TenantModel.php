<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
    /**
     * The database connection that should be used by the model.
     */
    protected $connection = 'tenant';
}
