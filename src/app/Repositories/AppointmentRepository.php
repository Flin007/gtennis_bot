<?php

namespace App\Repositories;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class AppointmentRepository extends ModelRepository
{
    public function getClassName(): string
    {
        return Appointment::class;
    }

    /**
     * @param int $userId
     *
     * @return mixed|null
     */
    public function getActiveAppointmentsByUserId(int $userId): mixed
    {
        return $this->createQueryBuilder()
            ->where('user_id', $userId)
            ->where('date', '>=', Carbon::now()->toDateString())
            ->where('status', 1)
            ->get();
    }

    public function getActiveAppointmentsByDate(string $date): Collection
    {
        return $this->createQueryBuilder()
            ->where('date', $date)
            ->where('status', 1)
            ->get();
    }
}
