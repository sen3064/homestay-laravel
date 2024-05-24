<?php
namespace  App;

use Illuminate\Database\Eloquent\Model;

class UserMeta extends Model
{
    protected $connection="kabtour_db";
    protected $table = 'user_meta';
}
