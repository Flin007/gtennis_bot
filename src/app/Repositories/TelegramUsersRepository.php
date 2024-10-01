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
     * @param int $userId
     *
     * @return mixed|null
     */
    public function findUserById(int $userId): mixed
    {
        return $this->createQueryBuilder()
            ->where('user_id', '=', $userId)
            ->first();
    }
}
