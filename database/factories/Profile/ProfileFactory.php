<?php

namespace Database\Factories\Profile;

use App\Models\Profile\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Profile::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            // Assuming each profile belongs to a user
            'user_id' => User::factory(),
            'bio' => $this->faker->paragraph,
            'twitter_handle' => '@' . $this->faker->userName,
            // Add other profile-specific fields here
        ];
    }
}
