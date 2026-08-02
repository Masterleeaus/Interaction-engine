<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Capabilities;

use TitanZero\Interaction\Contracts\CapabilityHandlerInterface;

class CrmCustomerCreateHandler implements CapabilityHandlerInterface
{
    public function handle(string $capability, array $payload): void
    {
        // Create customer in your CRM
        // $customer = Customer::create([
        //     'name' => $payload['name'],
        //     'email' => $payload['email'],
        //     'phone' => $payload['phone'],
        // ]);

        // Record event, dispatch notifications, etc.
        \Log::info('Customer created', ['payload' => $payload]);
    }
}