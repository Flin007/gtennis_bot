<?php

namespace App\Repositories;

use App\Models\TelegramUser;

class TelegramUsersRepository extends ModelRepository
{
    public function getClassName(): string
    {
        return TelegramUser::class;
    }

    /**
     * Returns the language selected by the user.
     * @param int $userId
     * @return string
     */
    public function getSelectedLanguageByUserId(int $userId): string
    {
        $lang = $this->createQueryBuilder()
            ->select('selected_language')
            ->where('user_id', $userId)
            ->first();
        return $lang ? $lang->selected_language : env('DEFAULT_LANGUAGE');
    }
}
