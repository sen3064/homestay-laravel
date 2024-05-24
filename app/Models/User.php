<?php
namespace App\Models;
class User extends \App\User
{
    protected $connection="kabtour_db";
    protected $table="users";
}