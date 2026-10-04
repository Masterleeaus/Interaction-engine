<?php

declare(strict_types=1);

namespace TitanZero\Engines\Executive\Implementations;

use TitanZero\Engines\Executive\Contracts\RiskEngineInterface;
class RiskEngine implements RiskEngineInterface
{
    private array $risks = [];
    private array $mitigations = [];

    public function assess(array $context): array
    {
        $findings = [];
        $value = max(0.0, (float) ($context['financial_value'] ?? 0));
        if ($value > 25000) {
            $findings[] = $this->finding('high_value_transaction', 'High value transaction', 30, ['financial_value' => $value]);
        } elseif ($value > 10000) {
            $findings[] = $this->finding('elevated_value_transaction', 'Elevated value transaction', 20, ['financial_value' => $value]);
        } elseif ($value > 5000) {
            $findings[] = $this->finding('moderate_value_transaction', 'Moderate value transaction', 10, ['financial_value' => $value]);
        }

        if (($context['new_customer'] ?? false) === true) {
            $findings[] = $this->finding('new_customer', 'New customer requires identity and scope checks', 10, ['new_customer' => true]);
        }
        $overdueDays = max(0, (int) ($context['overdue_days'] ?? 0));
        if ($overdueDays >= 30) {
            $findings[] = $this->finding('long_overdue_balance', 'Balance is more than 30 days overdue', 25, ['overdue_days' => $overdueDays]);
        } elseif ($overdueDays >= 8) {
            $findings[] = $this->finding('overdue_balance', 'Balance is more than 7 days overdue', 15, ['overdue_days' => $overdueDays]);
        }

        $discount = max(0.0, (float) ($context['discount_percent'] ?? 0));
        if ($discount > 25) {
            $findings[] = $this->finding('large_discount', 'Discount exceeds 25 percent', 20, ['discount_percent' => $discount]);
        } elseif ($discount > 10) {
            $findings[] = $this->finding('discount_review', 'Discount exceeds 10 percent', 10, ['discount_percent' => $discount]);
        }

        $complaints = max(0, (int) ($context['unresolved_complaints'] ?? 0));
        if ($complaints > 0) {
            $findings[] = $this->finding('unresolved_complaints', 'Customer has unresolved complaints', min(30, 8 * $complaints), ['unresolved_complaints' => $complaints]);
        }
        if (($context['required_evidence_missing'] ?? false) === true) {
            $findings[] = $this->finding('missing_evidence', 'Required evidence is missing', 25, ['required_evidence_missing' => true]);
        }
        $incident = strtolower((string) ($context['safety_incident_severity'] ?? ''));
        if (in_array($incident, ['critical', 'serious', 'high'], true)) {
            $findings[] = $this->finding('safety_incident', 'A serious safety incident needs human review', 40, ['severity' => $incident]);
        } elseif ($incident === 'moderate') {
            $findings[] = $this->finding('safety_incident', 'A safety incident needs review', 20, ['severity' => $incident]);
        }

        $score = min(100, array_sum(array_column($findings, 'score')));
        $this->risks = $findings;
        return [
            'score' => $score,
            'severity' => $this->severity($score),
            'risks' => $findings,
            'risk_count' => count($findings),
            'assessed_at' => gmdate(DATE_ATOM),
        ];
    }

    public function getRisks(): array
    {
        return $this->risks;
    }

    public function mitigate(string $risk, string $strategy): void
    {
        if (trim($strategy) === '') {
            throw new \InvalidArgumentException('A mitigation strategy is required.');
        }
        $matched = false;
        foreach ($this->risks as &$finding) {
            if (($finding['id'] ?? null) === $risk || ($finding['name'] ?? null) === $risk) {
                $finding['mitigation'] = $strategy;
                $finding['status'] = 'mitigation_recorded';
                $matched = true;
                $this->mitigations[$risk] = $strategy;
            }
        }
        unset($finding);
        if (!$matched) {
            throw new \InvalidArgumentException("Risk '{$risk}' is not part of the current assessment.");
        }
    }

    public function getRiskScore(array $context): float
    {
        return (float) $this->assess($context)['score'];
    }

    private function finding(string $id, string $name, int $score, array $evidence): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'score' => $score,
            'severity' => $this->severity($score),
            'evidence' => $evidence,
            'mitigation' => $this->mitigations[$id] ?? null,
            'status' => isset($this->mitigations[$id]) ? 'mitigation_recorded' : 'open',
        ];
    }

    private function severity(int $score): string
    {
        return match (true) {
            $score >= 75 => 'critical',
            $score >= 50 => 'high',
            $score >= 25 => 'moderate',
            default => 'low',
        };
    }
}
