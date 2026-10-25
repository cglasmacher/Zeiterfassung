<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class HaccpChecklistResult
{
    public function evaluate(array $configuration, mixed $payload): array
    {
        $items = collect($configuration['checklist_items'] ?? [])
            ->filter(fn ($item) => is_array($item) && filled($item['id'] ?? null) && filled($item['label'] ?? null))
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'payload' => 'Für diese Checkliste sind keine Prüfpunkte konfiguriert.',
            ]);
        }

        $answers = collect(is_array($payload) ? ($payload['checklist'] ?? []) : [])
            ->filter(fn ($answer) => is_array($answer) && array_key_exists('id', $answer))
            ->keyBy(fn ($answer) => (string) $answer['id']);

        $normalized = [];
        $failed = [];

        foreach ($items as $item) {
            $id = (string) $item['id'];
            $required = (bool) ($item['required'] ?? true);
            $answer = $answers->get($id);
            $hasValue = is_array($answer) && array_key_exists('ok', $answer) && is_bool($answer['ok']);

            if ($required && !$hasValue) {
                throw ValidationException::withMessages([
                    'payload' => 'Bitte alle Pflicht-Prüfpunkte der Checkliste beantworten.',
                ]);
            }

            $ok = $hasValue ? $answer['ok'] : null;
            $normalized[] = [
                'id' => $id,
                'label' => (string) $item['label'],
                'required' => $required,
                'ok' => $ok,
            ];

            if ($ok === false) {
                $failed[] = (string) $item['label'];
            }
        }

        return [
            'payload' => ['checklist' => $normalized],
            'within_limits' => count($failed) === 0,
            'failed_labels' => $failed,
        ];
    }
}
