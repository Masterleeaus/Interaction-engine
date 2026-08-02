<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Generator;

use TitanZero\Interaction\Contracts\GeneratorInterface;
use TitanZero\Interaction\DTO\InteractionDefinition;
use TitanZero\Interaction\DTO\Section;
use TitanZero\Interaction\DTO\Question;
use TitanZero\Interaction\Registry\InteractionRegistry;
use Illuminate\Support\Str;

class GeneratorEngine implements GeneratorInterface
{
    private InteractionRegistry $registry;
    private array $fragments = [];
    private array $templates = [];

    public function __construct(InteractionRegistry $registry)
    {
        $this->registry = $registry;
        $this->loadTemplates();
    }

    public function generate(array $requirements): InteractionDefinition
    {
        // Extract requirements
        $intent = $requirements['intent'] ?? 'unknown';
        $category = $requirements['category'] ?? 'general';
        $context = $requirements['context'] ?? [];

        // Build definition from fragments
        $sections = $this->buildSections($intent, $context);
        $capability = $this->mapToCapability($intent);
        $type = $this->determineType($requirements);

        $definition = new InteractionDefinition(
            id: $this->generateId($intent),
            version: '1.0.0',
            name: $this->generateName($intent),
            description: $this->generateDescription($intent, $context),
            category: $category,
            permissions: $this->generatePermissions($intent),
            sections: $sections,
            capability: $capability,
            type: $type,
            metadata: [
                'generated' => true,
                'timestamp' => now()->toIso8601String(),
                'requirements' => $requirements,
            ]
        );

        return $definition;
    }

    public function suggestInteractions(int $userId, array $context): array
    {
        // Suggest interactions based on context
        $suggestions = [];

        if (isset($context['customer_id'])) {
            $suggestions[] = [
                'id' => 'create_quote',
                'name' => 'Create Quote',
                'reason' => 'Customer selected',
                'confidence' => 0.8,
            ];
        }

        if (isset($context['job_id']) && isset($context['job_status']) && $context['job_status'] === 'completed') {
            $suggestions[] = [
                'id' => 'create_invoice',
                'name' => 'Create Invoice',
                'reason' => 'Job completed',
                'confidence' => 0.9,
            ];
        }

        return $suggestions;
    }

    private function buildSections(string $intent, array $context): array
    {
        $sections = [];

        // Always start with identity section
        $sections[] = new Section(
            id: 'identity',
            title: 'Basic Information',
            questions: [
                $this->createQuestion('name', 'What is the name?', 'text'),
            ],
        );

        // Add intent-specific sections
        $template = $this->findTemplate($intent);
        if ($template) {
            foreach ($template['sections'] as $section) {
                $sections[] = new Section(
                    id: $section['id'],
                    title: $section['title'],
                    questions: array_map(fn($q) => $this->createQuestion(
                        $q['key'],
                        $q['question'],
                        $q['response_type'] ?? 'text'
                    ), $section['questions'] ?? []),
                );
            }
        }

        // Always end with review section
        $sections[] = new Section(
            id: 'review',
            title: 'Review & Submit',
            questions: [],
        );

        return $sections;
    }

    private function createQuestion(string $key, string $question, string $responseType): Question
    {
        return new Question(
            key: $key,
            question: $question,
            responseType: $responseType,
            validation: ['required' => true],
        );
    }

    private function mapToCapability(string $intent): string
    {
        $mapping = [
            'create_quote' => 'quotes.create',
            'create_job' => 'jobs.create',
            'create_invoice' => 'finance.invoice.create',
            'new_customer' => 'crm.customer.create',
            'record_payment' => 'finance.payment.record',
        ];
        return $mapping[$intent] ?? 'generic.' . $intent;
    }

    private function determineType(array $requirements): string
    {
        if (isset($requirements['type'])) {
            return $requirements['type'];
        }
        return 'wizard';
    }

    private function generateId(string $intent): string
    {
        return $intent . '_' . Str::random(6);
    }

    private function generateName(string $intent): string
    {
        $names = [
            'create_quote' => 'Create Quote',
            'create_job' => 'Create Job',
            'new_customer' => 'New Customer',
            'create_invoice' => 'Create Invoice',
            'record_payment' => 'Record Payment',
        ];
        return $names[$intent] ?? ucfirst(str_replace('_', ' ', $intent));
    }

    private function generateDescription(string $intent, array $context): string
    {
        return "Auto-generated interaction for " . $this->generateName($intent);
    }

    private function generatePermissions(string $intent): array
    {
        $mapping = [
            'create_quote' => ['quotes.create'],
            'create_job' => ['jobs.create'],
            'new_customer' => ['crm.customers.create'],
            'create_invoice' => ['finance.invoices.create'],
            'record_payment' => ['finance.payments.record'],
        ];
        return $mapping[$intent] ?? ['interaction.use'];
    }

    private function loadTemplates(): void
    {
        $this->templates = config('interaction.templates', []);
    }

    private function findTemplate(string $intent): ?array
    {
        return $this->templates[$intent] ?? null;
    }
}