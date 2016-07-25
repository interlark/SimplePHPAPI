<?php

use Illuminate\Database\Eloquent\Model as Eloquent;

class File  extends Eloquent
{
    protected $fillable = ['filename', 'owner_id', 'compressed', 'locked'];
    protected $guarded = ['id'];
    public $timestamps = [];
}

?>
