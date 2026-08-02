<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Domain\Adapters;

use TitanZero\Interaction\Domain\DomainAdapter;
use TitanZero\Interaction\Infrastructure\WorkCore\GovernedWorkCoreDispatcher;

class WorkCoreAdapter extends DomainAdapter
{
    public function createCustomer(array $data): mixed
    {
        $name = $data['name']
            ?? $data['display_name']
            ?? $data['company_name']
            ?? trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name or display_name is required.');
        }

        return $this->create('customer', [
            'name' => $name,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address_line1' => $data['address_line1'] ?? $data['street'] ?? null,
            'address_suburb' => $data['address_suburb'] ?? $data['suburb'] ?? null,
            'address_city' => $data['address_city'] ?? $data['city'] ?? null,
            'address_state' => $data['address_state'] ?? $data['state'] ?? null,
            'address_postcode' => $data['address_postcode'] ?? $data['postcode'] ?? null,
            'customer_type' => $data['customer_type'] ?? 'individual',
            'company_name' => $data['company_name'] ?? (($data['customer_type'] ?? null) === 'business' ? $name : null),
            'communication_preference' => $data['communication_preference'] ?? null,
            'marketing_consent' => (bool) ($data['marketing_consent'] ?? false),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function createQuote(array $data): mixed
    {
        $this->requireFields($data, ['customer_id']);
        $services = $data['services'] ?? $data['service_items'] ?? null;
        if ($services === null && isset($data['service_id'])) {
            $services = [[
                'service_id' => $data['service_id'],
                'quantity' => $data['quantity'] ?? 1,
            ]];
        }

        return $this->create('quote', [
            'customer_id' => $data['customer_id'],
            'services' => $services ?? [],
            'site_address' => $data['site_address'] ?? $data['address'] ?? null,
            'access_instructions' => $data['access_instructions'] ?? null,
            'subtotal' => $data['subtotal'] ?? null,
            'discount' => $data['discount'] ?? 0,
            'total' => $data['total'] ?? $data['estimated_price'] ?? null,
            'valid_until' => $data['valid_until'] ?? $this->validUntil($data),
            'status' => $data['status'] ?? 'draft',
            'send_to_customer' => (bool) ($data['send_to_customer'] ?? false),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function createJob(array $data): mixed
    {
        $this->requireFields($data, ['customer_id']);
        return $this->create('job', [
            'customer_id' => $data['customer_id'],
            'service_id' => $data['service_id'] ?? null,
            'property_id' => $data['property_id'] ?? null,
            'services' => $data['services'] ?? [],
            'description' => $data['description'] ?? '',
            'address' => $data['address'] ?? $data['site_address'] ?? null,
            'access_instructions' => $data['access_instructions'] ?? null,
            'scheduled_start' => $data['scheduled_start'] ?? null,
            'scheduled_end' => $data['scheduled_end'] ?? null,
            'preferred_date' => $data['preferred_date'] ?? $data['scheduled_date'] ?? null,
            'preferred_time' => $data['preferred_time'] ?? '09:00',
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'worker_ids' => array_values((array) ($data['worker_ids'] ?? [])),
            'checklist_id' => $data['checklist_id'] ?? null,
            'price' => $data['price'] ?? null,
            'status' => $data['status'] ?? 'scheduled',
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function completeJob(array $data): mixed
    {
        $this->requireFields($data, ['job_id']);
        $payload = [
            'job_id' => $data['job_id'],
            'work_order' => $data['job_id'],
            'status' => 'completed',
            'completed_at' => $data['completed_at'] ?? gmdate(DATE_ATOM),
            'checklist_complete' => (bool) ($data['checklist_complete'] ?? false),
            'actual_duration_minutes' => $data['actual_duration_minutes'] ?? null,
            'evidence_attachment_ids' => array_values((array) ($data['evidence_attachment_ids'] ?? [])),
            'damage_reported' => (bool) ($data['damage_reported'] ?? false),
            'exception_notes' => $data['exception_notes'] ?? null,
            'customer_signature' => $data['customer_signature'] ?? null,
            'request_invoice' => (bool) ($data['request_invoice'] ?? false),
        ];
        $service = $this->config['services']['job_completion'] ?? null;
        if (is_callable($service)) { return $service($payload); }
        if (is_string($service) && class_exists($service)) {
            $instance = app($service);
            foreach (['complete', 'handle', 'update'] as $method) {
                if (method_exists($instance, $method)) { return $instance->{$method}($payload); }
            }
        }
        return (new GovernedWorkCoreDispatcher())->dispatch('workcore.work_order.change_status', $payload);
    }

    public function createInvoice(array $data): mixed
    {
        $this->requireFields($data, ['customer_id']);
        return $this->create('invoice', [
            'customer_id' => $data['customer_id'],
            'job_id' => $data['job_id'] ?? null,
            'line_items' => array_values((array) ($data['line_items'] ?? [])),
            'subtotal' => $data['subtotal'] ?? null,
            'tax' => $data['tax'] ?? null,
            'total' => $data['total'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'due_in_days' => $data['due_in_days'] ?? 30,
            'payment_method' => $data['payment_method'] ?? null,
            'send_to_customer' => (bool) ($data['send_to_customer'] ?? false),
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'draft',
        ]);
    }

    public function recordPayment(array $data): mixed
    {
        $this->requireFields($data, ['invoice_id', 'amount']);
        return $this->create('payment', [
            'invoice_id' => $data['invoice_id'],
            'amount' => $data['amount'],
            'payment_date' => $data['payment_date'] ?? date('Y-m-d'),
            'payment_method' => $data['payment_method'] ?? 'cash',
            'reference' => $data['reference'] ?? null,
        ]);
    }

    private function create(string $entity, array $attributes): mixed
    {
        $service = $this->config['services'][$entity] ?? null;
        if (is_callable($service)) { return $service($attributes); }
        if (is_string($service) && class_exists($service)) {
            $instance = app($service);
            foreach (['create', 'handle'] as $method) {
                if (method_exists($instance, $method)) { return $instance->{$method}($attributes); }
            }
        }

        $actionKeys = [
            'customer' => 'workcore.customer.create',
            'job' => 'workcore.work_order.create',
        ];
        if (isset($actionKeys[$entity])) {
            return (new GovernedWorkCoreDispatcher())->dispatch($actionKeys[$entity], $attributes);
        }

        // Finance actions are intentionally fail-closed until the host's
        // governed Titan Money action keys are explicitly mapped.
        throw new \RuntimeException("No governed WorkCore action is registered for '{$entity}'. Direct model writes are prohibited.");
    }

    private function requireFields(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                throw new \InvalidArgumentException("{$field} is required.");
            }
        }
    }

    private function validUntil(array $data): string
    {
        $days = max(1, (int) ($data['valid_days'] ?? 14));
        return date('Y-m-d', strtotime("+{$days} days"));
    }
}
