<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'user_id'           => User::factory(),
            'uuid'              => Str::uuid()->toString(),
            'original_filename' => fake()->word() . '.jpg',
            'stored_filename'   => Str::uuid()->toString() . '.jpg',
            'mime_type'         => 'image/jpeg',
            'file_size'         => fake()->numberBetween(100000, 5000000),
            'status'            => Media::STATUS_PENDING,
            'processing_step'   => null,
            'progress'          => 0,
            'error_message'     => null,
            'outputs'           => null,
        ];
    }
}
