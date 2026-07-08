<?php

namespace QueueSql\Tests\Support;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    protected $guarded = [];
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';
}
