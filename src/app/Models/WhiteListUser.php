<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhiteListUser extends Model
{
    use HasFactory;
    protected $table = 'white_list_user_ids';
    protected $guarded = false;
}
