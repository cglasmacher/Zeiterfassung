<?php

namespace App\Http\Controllers;

use App\Models\AccountingAccount;
use App\Models\AccountingDocument;
use App\Models\AccountingDocumentLine;
use App\Models\AccountingRule;
use App\Models\AccountingSupplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AccountingReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AccountingDocument::query()
            ->with('supplier:id,name,creditor_account')
            ->withCount([
                'lines',
                'lines as unresolved_lines_count' => fn ($q) => $q->where(function ($lines) {
                    $lines->whereNull('account_id')->orWhereIn('status', ['review', 'manual']);
                }),
            ])
            ->latest('invoice_date')
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', $term)
                    ->orWhere('supplier_name_raw', 'like', $term)
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', $term));
            });
        }

        return response()->json([
            'documents' => $query->paginate(30),
            'counts' => [
                'received' => AccountingDocument::where('status', 'received')->count(),
                'review' => AccountingDocument::whereIn('status', ['analyzed', 'review'])->count(),
                'ready' => AccountingDocument::where('status', 'ready')->count(),
                'approved' => AccountingDocument::where('status', 'approved')->count(),
                'exported' => AccountingDocument::where('status', 'exported')->count(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $document = AccountingDocument::with([
            'supplier:id,name,creditor_account',
            'lines.account:id,account_number,name',
            'approver:id,name',
        ])->findOrFail($id);

        return response()->json([
            'document' => $document,
            'accounts' => AccountingAccount::where('active', true)->orderBy('account_number')->get(['id', 'account_number', 'name', 'kind']),
        ]);
    }

    public function pdf(int $id): BinaryFileResponse
    {
        $document = AccountingDocument::findOrFail($id);
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        $path = Storage::disk('local')->path($document->file_path);
        $name = $document->original_filename ?: 'beleg-'.$document->id.'.pdf';

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $name).'"',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->filled('payload') && is_string($request->input('payload'))) {
            $decoded = json_decode($request->input('payload'), true);
            if (! is_array($decoded)) {
                throw ValidationException::withMessages(['payload' => 'payload enthält kein gültiges JSON.']);
            }
            $request->merge($decoded);
        }

        $data = $request->validate([
            'source' => ['nullable', 'string', 'max:32'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'integer', 'exists:accounting_suppliers,id'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'service_date_from' => ['nullable', 'date'],
            'service_date_to' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'net_cents' => ['nullable', 'integer'],
            'tax_cents' => ['nullable', 'integer'],
            'gross_cents' => ['nullable', 'integer'],
            'confidence' => ['nullable', 'integer', 'between:0,100'],
            'original_filename' => ['nullable', 'string', 'max:255'],
            'file_path' => ['nullable', 'string', 'max:1024'],
            'raw_payload' => ['nullable', 'array'],
            'validation_errors' => ['nullable', 'array'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.position_no' => ['nullable', 'integer', 'min:0'],
            'lines.*.article_number' => ['nullable', 'string', 'max:255'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.quantity' => ['nullable', 'numeric'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.net_cents' => ['nullable', 'integer'],
            'lines.*.tax_rate' => ['nullable', 'numeric', Rule::in([0, 7, 19])],
            'lines.*.tax_cents' => ['nullable', 'integer'],
            'lines.*.gross_cents' => ['nullable', 'integer'],
            'lines.*.account_number' => ['nullable', 'string', 'max:20'],
            'lines.*.confidence' => ['nullable', 'integer', 'between:0,100'],
            'lines.*.classification_source' => ['nullable', 'string', 'max:24'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $document = DB::transaction(function () use ($data, $request) {
            $supplier = $this->resolveSupplier($data['supplier_id'] ?? null, $data['supplier_name'] ?? null);
            $status = ! empty($data['validation_errors']) ? 'review' : 'analyzed';

            $lookup = array_filter([
                'source' => $data['source'] ?? 'n8n',
                'source_reference' => $data['source_reference'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            $document = $lookup && isset($lookup['source_reference'])
                ? AccountingDocument::firstOrNew($lookup)
                : new AccountingDocument();

            $document->fill([
                'source' => $data['source'] ?? 'n8n',
                'source_reference' => $data['source_reference'] ?? null,
                'supplier_id' => $supplier?->id,
                'supplier_name_raw' => $data['supplier_name'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'service_date_from' => $data['service_date_from'] ?? null,
                'service_date_to' => $data['service_date_to'] ?? null,
                'currency' => strtoupper($data['currency'] ?? 'EUR'),
                'net_cents' => $data['net_cents'] ?? null,
                'tax_cents' => $data['tax_cents'] ?? null,
                'gross_cents' => $data['gross_cents'] ?? null,
                'confidence' => $data['confidence'] ?? null,
                'status' => $status,
                'original_filename' => $data['original_filename'] ?? null,
                'file_path' => $data['file_path'] ?? $document->file_path,
                'raw_payload' => $data['raw_payload'] ?? null,
                'validation_errors' => $data['validation_errors'] ?? null,
            ]);
            $document->save();

            if ($request->hasFile('file')) {
                if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
                    Storage::disk('local')->delete($document->file_path);
                }
                $file = $request->file('file');
                $filename = $document->id.'-'.bin2hex(random_bytes(8)).'.pdf';
                $path = $file->storeAs('accounting-documents', $filename, 'local');
                $document->update([
                    'file_path' => $path,
                    'original_filename' => $data['original_filename'] ?? $file->getClientOriginalName(),
                ]);
            }

            $document->lines()->delete();

            foreach ($data['lines'] as $line) {
                $resolved = $this->resolveAccounting($supplier, $line);
                $account = $resolved['account'];
                $confidence = $line['confidence'] ?? $resolved['confidence'];
                $taxRate = $line['tax_rate'] ?? $resolved['tax_rate'];
                $lineStatus = $account && $confidence >= 90 ? 'proposed' : 'review';

                $document->lines()->create([
                    ...$line,
                    'tax_rate' => $taxRate,
                    'account_id' => $account?->id,
                    'account_number_snapshot' => $account?->account_number,
                    'classification_source' => $line['classification_source'] ?? $resolved['source'],
                    'confidence' => $confidence,
                    'status' => $lineStatus,
                    'rule_key' => $resolved['rule_key'],
                ]);
            }

            $this->refreshStatus($document);

            return $document;
        });

        return response()->json(['document' => $document->fresh(['supplier', 'lines.account'])], 201);
    }

    public function updateLine(Request $request, int $documentId, int $lineId): JsonResponse
    {
        $line = AccountingDocumentLine::where('document_id', $documentId)->findOrFail($lineId);
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'note' => ['nullable', 'string', 'max:2000'],
            'remember_rule' => ['sometimes', 'boolean'],
        ]);

        $account = AccountingAccount::findOrFail($data['account_id']);

        DB::transaction(function () use ($line, $account, $data) {
            $line->update([
                'account_id' => $account->id,
                'account_number_snapshot' => $account->account_number,
                'classification_source' => 'manual',
                'confidence' => 100,
                'status' => 'approved',
                'note' => $data['note'] ?? $line->note,
            ]);

            if (! empty($data['remember_rule'])) {
                $matchType = $line->article_number ? 'article_number' : 'description_exact';
                $matchValue = $line->article_number ?: trim($line->description);

                AccountingRule::updateOrCreate(
                    [
                        'supplier_id' => $line->document->supplier_id,
                        'match_type' => $matchType,
                        'match_value' => $matchValue,
                    ],
                    [
                        'account_id' => $account->id,
                        'tax_rate' => $line->tax_rate,
                        'priority' => $line->article_number ? 10 : 50,
                        'learned_from_manual' => true,
                        'active' => true,
                    ]
                );
            }

            $this->refreshStatus($line->document);
        });

        return response()->json(['line' => $line->fresh('account'), 'document_status' => $line->document->fresh()->status]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $document = AccountingDocument::with('lines')->findOrFail($id);

        if ($document->lines->isEmpty()) {
            throw ValidationException::withMessages(['document' => 'Der Beleg enthält keine Buchungszeilen.']);
        }

        if ($document->lines->contains(fn ($line) => ! $line->account_id)) {
            throw ValidationException::withMessages(['document' => 'Nicht alle Positionen sind kontiert.']);
        }

        $lineGross = $document->lines->sum(fn ($line) => $line->gross_cents ?? (($line->net_cents ?? 0) + ($line->tax_cents ?? 0)));
        if ($document->gross_cents !== null && abs($lineGross - $document->gross_cents) > 2) {
            throw ValidationException::withMessages([
                'document' => 'Die Summe der Buchungszeilen weicht vom Rechnungsbrutto ab.',
            ]);
        }

        $document->lines()->update(['status' => 'approved']);
        $document->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json(['document' => $document->fresh(['supplier', 'lines.account', 'approver'])]);
    }

    private function resolveSupplier(?int $supplierId, ?string $name): ?AccountingSupplier
    {
        if ($supplierId) {
            return AccountingSupplier::find($supplierId);
        }
        if (! $name) {
            return null;
        }

        $normalized = mb_strtolower(trim($name));
        return AccountingSupplier::whereRaw('LOWER(name) = ?', [$normalized])->get()->first()
            ?? AccountingSupplier::where('active', true)->get()->first(function ($supplier) use ($normalized) {
                return collect($supplier->aliases ?? [])->contains(fn ($alias) => mb_strtolower(trim($alias)) === $normalized);
            });
    }

    private function resolveAccounting(?AccountingSupplier $supplier, array $line): array
    {
        if (! empty($line['account_number'])) {
            $account = AccountingAccount::where('account_number', $line['account_number'])->where('active', true)->first();
            if ($account) {
                return [
                    'account' => $account,
                    'tax_rate' => null,
                    'confidence' => $line['confidence'] ?? 95,
                    'source' => $line['classification_source'] ?? 'n8n',
                    'rule_key' => null,
                ];
            }
        }

        if (! $supplier) {
            return $this->unresolvedAccounting();
        }

        $description = mb_strtolower(trim($line['description'] ?? ''));
        $articleNumber = trim((string) ($line['article_number'] ?? ''));
        $rules = AccountingRule::with('account')
            ->where('supplier_id', $supplier->id)
            ->where('active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            $matchValue = mb_strtolower(trim($rule->match_value));
            $matches = match ($rule->match_type) {
                'article_number' => $articleNumber !== '' && strcasecmp($articleNumber, $rule->match_value) === 0,
                'description_exact' => $description !== '' && $description === $matchValue,
                'description_contains' => $description !== '' && $matchValue !== '' && str_contains($description, $matchValue),
                'supplier_default' => true,
                default => false,
            };

            if (! $matches || ! $rule->account?->active) {
                continue;
            }

            return [
                'account' => $rule->account,
                'tax_rate' => $rule->tax_rate !== null ? (float) $rule->tax_rate : null,
                'confidence' => $rule->learned_from_manual ? 100 : 98,
                'source' => $rule->learned_from_manual ? 'learned_rule' : 'rule',
                'rule_key' => $rule->match_type.':'.$rule->match_value,
            ];
        }

        return $this->unresolvedAccounting();
    }

    private function unresolvedAccounting(): array
    {
        return [
            'account' => null,
            'tax_rate' => null,
            'confidence' => 0,
            'source' => 'unresolved',
            'rule_key' => null,
        ];
    }

    private function refreshStatus(AccountingDocument $document): void
    {
        $document->load('lines');
        $hasUnassigned = $document->lines->contains(fn ($line) => ! $line->account_id);
        $hasReview = $document->lines->contains(fn ($line) => in_array($line->status, ['review', 'manual'], true));

        $document->update([
            'status' => ($hasUnassigned || $hasReview) ? 'review' : 'ready',
        ]);
    }
}
