<?php

namespace App\Repositories;

use App\Models\Appointment;
use Carbon\Carbon;

class AppointmentRepository extends ModelRepository
{
    public function getClassName(): string
    {
        return Appointment::class;
    }

    /**
     * @param $userId
     *
     * @return mixed|null
     */
    public function getAppointmentsByUserId($userId): mixed
    {
        return $this->createQueryBuilder()
            ->where('user_id', $userId)
            ->where('date' >= Carbon::now()->toDateString())
            ->get();
    }
}
