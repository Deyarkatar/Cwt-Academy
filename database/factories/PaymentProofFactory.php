<?php

namespace Database\Factories;

use App\Enums\PaymentProofStatus;
use App\Models\CourseRequest;
use App\Models\PaymentProof;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentProof> */
class PaymentProofFactory extends Factory
{
    protected $model = PaymentProof::class;

    public function definition(): array
    {
        return [
            'course_request_id' => CourseRequest::factory(),
            'amount_iqd' => fake()->numberBetween(50000, 500000),
            'sender_name' => fake()->name(),
            'transaction_reference' => fake()->unique()->uuid(),
            'status' => PaymentProofStatus::PENDING,
        ];
    }
}
