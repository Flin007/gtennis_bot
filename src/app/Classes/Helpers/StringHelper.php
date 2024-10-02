<?php

namespace App\Classes\Helpers;

class StringHelper
{
    /**
     * Приводит первую букву строки к заглавной, для кириллицы не работает ucfirst.
     *
     * @param $text
     *
     * @return string
     */
    public static function mb_ucfirst($text) {
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }
}
