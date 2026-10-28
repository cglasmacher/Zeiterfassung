<?php

namespace App\Http\Controllers;

use App\Models\AccountingAccount;
use App\Models\AccountingDocument;
use App\Models\AccountingDocumentLine;
use App\Models\AccountingRule;
use App\Models\AccountingSupplier;
use App\Services\Accounting\DocumentValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AccountingReviewController extends Controller
{
    public function inspect(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:pdf', 'max:20480']]);

        return response()->json(app(\App\Services\Accounting\InvoiceSupplierDetector::class)
            ->detect($request->file('file')->getRealPath()));
    }

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
                'review' => AccountingDocument::whereIn('status', ['analyzed', 'review', 'analysis_failed'])->count(),
                'ready' => AccountingDocument::where('status', 'ready')->count(),
                'approved' => AccountingDocument::where('status', 'approved')->count(),
                'exported' => AccountingDocument::where('status', 'exported')->count(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $document = AccountingDocument::with([
            'supplier:id,name,creditor_account,active',
            'lines.account:id,account_number,name,active',
            'approver:id,name',
        ])->findOrFail($id);

        return response()->json([
            'document' => $document,
            'locked' => $document->isLocked(),
            'blocking_errors' => $document->isLocked() ? [] : app(DocumentValidation::class)->errors($document),
            'booking_summary' => app(\App\Services\Accounting\BookingSummary::class)->forDocument($document),
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

        if ($request->input('extraction_profile') === 'gato_layout_v1') {
            $request->validate(['supplier_name' => ['required', 'string', 'max:255']]);
            $supplier = $this->resolveSupplier(null, $request->input('supplier_name'));
            if ($supplier?->creditor_account !== '70007') {
                throw ValidationException::withMessages(['supplier_name' => 'Gato-Profil benötigt den aktiven Kreditor 70007.']);
            }
            $request->merge([
                ...app(\App\Services\Accounting\GatoImport::class)->prepare($request),
                'supplier_id' => $supplier->id,
            ]);
        }

        if ($request->input('extraction_profile') === 'handelshof_facturx_v1') {
            $supplier = AccountingSupplier::where('creditor_account', '70012')->where('active', true)->first();
            if (! $supplier) {
                throw ValidationException::withMessages(['supplier_name' => 'Handelshof-Profil benötigt den aktiven Kreditor 70012.']);
            }
            $request->merge([
                ...app(\App\Services\Accounting\HandelshofImport::class)->prepare($request),
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
            ]);
        }

        $data = $request->validate([
            'source' => ['nullable', 'string', 'max:32'],
            'source_reference' => ['required', 'string', 'max:255'],
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
            'confidence' => ['nullable', 'numeric', 'between:0,100'],
            'original_filename' => ['nullable', 'string', 'max:255'],
            'raw_payload' => ['nullable', 'array'],
            'validation_errors' => ['nullable', 'array'],
            'validation_errors.*' => ['string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.position_no' => ['nullable', 'integer', 'min:0'],
            'lines.*.article_number' => ['nullable', 'string', 'max:255'],
            'lines.*.invoice_group' => ['nullable', 'string', 'max:255'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.quantity' => ['nullable', 'numeric'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.net_cents' => ['nullable', 'integer'],
            'lines.*.tax_rate' => ['nullable', 'numeric', Rule::in([0, 7, 19])],
            'lines.*.tax_cents' => ['nullable', 'integer'],
            'lines.*.gross_cents' => ['nullable', 'integer'],
            'lines.*.account_number' => ['nullable', 'string', 'max:20'],
            'lines.*.confidence' => ['nullable', 'numeric', 'between:0,100'],
            'lines.*.classification_source' => ['nullable', 'string', 'max:24'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        // Confidence is a percentage. Accept decimal percentages, but never infer a 0..1 scale.
        if (isset($data['confidence'])) {
            $data['confidence'] = (int) floor((float) $data['confidence']);
        }
        foreach ($data['lines'] as &$line) {
            if (isset($line['confidence'])) {
                $line['confidence'] = (int) floor((float) $line['confidence']);
            }
        }
        unset($line);

        $document = DB::transaction(function () use ($data, $request) {
            $supplier = $this->resolveSupplier($data['supplier_id'] ?? null, $data['supplier_name'] ?? null);
            $status = ! empty($data['validation_errors']) ? 'review' : 'analyzed';

            // The unique source key and firstOrCreate also cover simultaneous retries.
            $document = AccountingDocument::firstOrCreate([
                'source' => $data['source'] ?? 'n8n',
                'source_reference' => $data['source_reference'],
            ]);
            $created = $document->wasRecentlyCreated;
            $document = AccountingDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if (! $created) {
                // Retries never replace analysis, manual corrections, PDF or approval.
                return ['document' => $document, 'duplicate' => true];
            }

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
                'file_path' => $document->file_path,
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
                $confidence = $resolved['confidence'];
                $taxRate = $line['tax_rate'] ?? null;
                $lineStatus = $account && $confidence >= 90 ? 'proposed' : 'review';

                // Group evidence stays in raw_payload; it is not a document-line column.
                unset($line['invoice_group']);

                $document->lines()->create([
                    ...$line,
                    'tax_rate' => $taxRate,
                    'account_id' => $account?->id,
                    'account_number_snapshot' => $account?->account_number,
                    'classification_source' => $resolved['source'],
                    'confidence' => $confidence,
                    'status' => $lineStatus,
                    'rule_key' => $resolved['rule_key'],
                ]);
            }

            $this->refreshStatus($document);

            return ['document' => $document, 'duplicate' => false];
        });

        return response()->json([
            'document' => $document['document']->fresh(['supplier', 'lines.account']),
            'duplicate' => $document['duplicate'],
        ], $document['duplicate'] ? 200 : 201);
    }

    public function updateLine(Request $request, int $documentId, int $lineId): JsonResponse
    {
        $line = AccountingDocumentLine::where('document_id', $documentId)->findOrFail($lineId);
        $data = $request->validate([
            'account_id' => ['required', 'integer', Rule::exists('accounting_accounts', 'id')->where('active', true)],
            'note' => ['nullable', 'string', 'max:2000'],
            'remember_rule' => ['sometimes', 'boolean'],
        ]);

        $account = AccountingAccount::findOrFail($data['account_id']);

        DB::transaction(function () use ($line, $account, $data) {
            $document = AccountingDocument::whereKey($line->document_id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($document);
            if (! empty($data['remember_rule']) && ! $document->supplier_id) {
                throw ValidationException::withMessages(['remember_rule' => 'Eine Lernregel benötigt einen erkannten Lieferanten.']);
            }
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
                        'tax_rate' => null,
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
        $document = DB::transaction(function () use ($request, $id) {
            $document = AccountingDocument::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($document);
            $errors = app(DocumentValidation::class)->errors($document);
            if ($errors) {
                throw ValidationException::withMessages(['document' => $errors]);
            }

            $document->lines()->update(['status' => 'approved']);
            $document->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            return $document;
        });

        return response()->json(['document' => $document->fresh(['supplier', 'lines.account', 'approver'])]);
    }

    private function assertEditable(AccountingDocument $document): void
    {
        abort_if($document->isLocked(), 409, 'Freigegebene oder exportierte Belege dürfen nicht verändert werden.');
    }

    public function destroy(int $id): \Illuminate\Http\Response
    {
        DB::transaction(function () use ($id) {
            $document = AccountingDocument::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($document);
            $path = $document->file_path;
            $document->delete(); // Positions are removed by the existing cascading foreign key.

            DB::afterCommit(function () use ($path) {
                if (! $path || AccountingDocument::where('file_path', $path)->exists()) {
                    return;
                }
                try {
                    if (! Storage::disk('local')->delete($path)) {
                        \Illuminate\Support\Facades\Log::warning('Original-PDF nach Beleglöschung nicht entfernt.', ['path' => $path]);
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });
        });

        return response()->noContent();
    }

    private function resolveSupplier(?int $supplierId, ?string $name): ?AccountingSupplier
    {
        if ($supplierId) {
            return AccountingSupplier::where('active', true)->find($supplierId);
        }
        if (! $name) {
            return null;
        }

        $normalized = mb_strtolower(trim($name));

        return AccountingSupplier::where('active', true)->whereRaw('LOWER(name) = ?', [$normalized])->get()->first()
            ?? AccountingSupplier::where('active', true)->get()->first(function ($supplier) use ($normalized) {
                return collect($supplier->aliases ?? [])->contains(fn ($alias) => mb_strtolower(trim($alias)) === $normalized);
            });
    }

    private function resolveAccounting(?AccountingSupplier $supplier, array $line): array
    {
        $description = mb_strtolower(trim($line['description'] ?? ''));
        $articleNumber = trim((string) ($line['article_number'] ?? ''));
        $rules = AccountingRule::with('account')
            ->where('supplier_id', $supplier?->id)
            ->whereNotNull('supplier_id')
            ->where('active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->sortBy(function ($rule) {
                return match (true) {
                    $rule->learned_from_manual && $rule->match_type === 'article_number' => 0,
                    $rule->learned_from_manual && $rule->match_type === 'description_exact' => 1,
                    $rule->learned_from_manual => 2,
                    default => 3,
                };
            });

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
                'tax_rate' => null,
                'confidence' => $rule->learned_from_manual ? 100 : 98,
                'source' => $rule->learned_from_manual ? 'learned_rule' : 'rule',
                'rule_key' => $rule->match_type.':'.$rule->match_value,
            ];
        }

        if ($supplier?->creditor_account === '70007') {
            $gato = app(\App\Services\Accounting\GatoAccount::class)->resolve($line);
            if ($gato) {
                $account = AccountingAccount::where('account_number', $gato[0])->where('active', true)->first();

                return ['account' => $account, 'tax_rate' => null, 'confidence' => $account ? 99 : 0,
                    'source' => 'supplier_rule', 'rule_key' => $gato[1]];
            }
        }

        if ($supplier?->creditor_account === '70012' && ! empty($line['invoice_group'])) {
            $match = app(\App\Services\Accounting\HandelshofAccount::class)->resolve($line);
            if ($match) {
                $account = AccountingAccount::where('account_number', $match[0])->where('active', true)->first();

                return ['account' => $account, 'tax_rate' => null, 'confidence' => $account ? 99 : 0,
                    'source' => 'supplier_rule', 'rule_key' => $match[1]];
            }
        }

        if (! empty($line['account_number'])) {
            $account = AccountingAccount::where('account_number', $line['account_number'])->where('active', true)->first();
            if ($account) {
                return [
                    'account' => $account,
                    'tax_rate' => null,
                    'confidence' => $line['confidence'] ?? 0,
                    'source' => 'ai',
                    'rule_key' => null,
                ];
            }
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
        if ($document->isLocked()) {
            return;
        }
        $document->unsetRelation('lines');
        $errors = app(DocumentValidation::class)->errors($document);
        $document->update(['status' => ! empty($document->raw_payload['analysis_failed'])
            ? 'analysis_failed' : ($errors ? 'review' : 'ready')]);
    }
}
