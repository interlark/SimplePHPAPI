<?php

use Illuminate\Database\Eloquent\Model as Eloquent;

class User extends Eloquent
{
    protected $fillable = ['username', 'password', 'apikey'];
    protected $guarded = ['id'];
    public $timestamps = [];
}

?>
