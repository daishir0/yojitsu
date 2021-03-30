<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Jisseki extends Model
{
    protected $guarded = [];
    protected $dates = ['date'];

    //------------------------------------------
    // 年/月/日のフォーマット表示
    public static function format($date)
    {
        return date_format($date, 'Y/m/d');
    }

    //------------------------------------------
    // 文字列からDateTime型を作成
    public static function todate($str_date)
    {
        return date_create($str_date);
    }
}
