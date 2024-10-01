<?php

namespace App\Repositories;

use App\Models\WhiteListUser;

class WhiteListUserRepository extends ModelRepository
{
    public function getClassName(): string
    {
        return WhiteListUser::class;
    }

    /**
     * @param $userId
     *
     * @return mixed|null
     */
    public function findUserById($userId): mixed
    {
        return $this->createQueryBuilder()
            ->where('user_id', $userId)
            ->first();
    }
}
