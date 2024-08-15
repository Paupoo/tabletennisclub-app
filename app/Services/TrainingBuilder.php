<?php

namespace App\Services;

use App\Models\Room;
use App\Models\Season;
use App\Models\Training;
use App\Models\User;
use Carbon\Carbon;

class TrainingBuilder
{
    private Training $training;

    public function __construct()
    {
        $this->training = new Training();
    }

    public function mergeDateAndTime(Carbon $date, string $start_time, string $end_time): self
    {
        $this->training->start = $date->setTimeFrom($start_time)->format('Y-m-d H:i');
        $this->training->end = $date->setTimeFrom($end_time)->format('Y-m-d H:i');

        return $this;
    }

    public function setAttributes(array $validated):self
    {
        $this->training->fill($validated);

        return $this;
    }

    public function setRoom(int $room_id): self
    {
        $room = Room::findOrFail($room_id);

        $this->training->room()->associate($room);
        
        return $this;
    }

    public function setSeason(int $season_id): self
    {
        $season = Season::findOrFail($season_id);

        $this->training->season()->associate($season);

        return $this;
    }

    public function setTrainer(int $trainer_id = null): self
    {
        if ($trainer_id !== null) {
            $trainer = User::findOrFail($trainer_id);

            $this->training->trainer()->associate($trainer);
        }

        return $this;
    }

    public function buildAndSave(): Training
    {
        $training = $this->training;
        $training->save();

        $this->training = new Training();

        return $training;
    }


        
}