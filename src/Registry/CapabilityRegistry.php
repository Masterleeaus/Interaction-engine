<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Registry;

use TitanZero\Interaction\Contracts\CapabilityHandlerInterface;
use TitanZero\Interaction\Contracts\DomainAdapterInterface;

class CapabilityRegistry
{
    private array $handlers = [];

    public function __construct(private readonly DomainAdapterInterface $domainAdapter)
    {
        $this->registerDefaultHandlers();
    }

    public function register(string $capability, callable $handler): void
    {
        $this->handlers[$capability] = $handler;
    }

    public function registerHandler(string $capability, CapabilityHandlerInterface $handler): void
    {
        $this->handlers[$capability] = static fn(array $payload): mixed => $handler->handle($capability, $payload);
    }

    public function getHandler(string $capability): callable
    {
        if (!isset($this->handlers[$capability])) {
            throw new \RuntimeException("No handler for capability '{$capability}'.");
        }
        return $this->handlers[$capability];
    }

    public function has(string $capability): bool
    {
        return isset($this->handlers[$capability]);
    }

    private function registerDefaultHandlers(): void
    {
        $this->register('crm.customer.create', fn(array $payload): mixed => $this->domainAdapter->createCustomer($payload));
        $this->register('quotes.create', fn(array $payload): mixed => $this->domainAdapter->createQuote($payload));
        $this->register('jobs.create', fn(array $payload): mixed => $this->domainAdapter->createJob($payload));
        $this->register('jobs.complete', fn(array $payload): mixed => $this->domainAdapter->completeJob($payload));
        $this->register('finance.invoice.create', fn(array $payload): mixed => $this->domainAdapter->createInvoice($payload));
        $this->register('finance.payment.record', fn(array $payload): mixed => $this->domainAdapter->recordPayment($payload));
    }
}
