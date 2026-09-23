<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhatsappMessage>
 */
class WhatsappMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'response'     => json_encode(['messages' => [['id' => 'wamid.' . fake()->uuid()]]]),
            'recipient_id' => '57' . fake()->numerify('3#########'),
            'deleted'      => 0,
            'type'         => 'carnet',
        ];
    }

    // A send Meta rejected — no messages[0].id in the response.
    public function failed(): static
    {
        return $this->state(fn () => [
            'response' => json_encode(['error' => ['message' => 'Template not found']]),
        ]);
    }
}
