<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramUser extends Model
{
    use HasFactory;
    protected $table = 'telegram_users';
    //Защищаем от хаписи колонку is_admin, чтобы её можно было менять только на прямую в бд
    protected $guarded = ['is_admin'];
}
