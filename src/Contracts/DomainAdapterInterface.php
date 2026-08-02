<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Contracts;

interface DomainAdapterInterface
{
    public function createCustomer(array $data): mixed;
    public function createQuote(array $data): mixed;
    public function createJob(array $data): mixed;
    public function completeJob(array $data): mixed;
    public function createInvoice(array $data): mixed;
    public function recordPayment(array $data): mixed;
}