<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\JournalEntryRequest;
use App\Models\Account;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Services\JournalEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class JournalEntryController extends Controller
{
    public function __construct(private TenantContext $tenant, private JournalEntryService $service)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', JournalEntry::class);
        $entries = JournalEntry::query()->where('company_id', $this->tenant->companyId())
            ->when(request('status'), fn ($query, $status) => $query->where('status', $status))
            ->when(request('entry_type'), fn ($query, $type) => $query->where('entry_type', $type))
            ->latest('entry_date')->paginate(30)->withQueryString();

        return view('accounting.journals.index', compact('entries'));
    }

    public function create(): View
    {
        $this->authorize('create', JournalEntry::class);

        return view('accounting.journals.form', $this->formData(new JournalEntry()));
    }

    public function store(JournalEntryRequest $request): RedirectResponse
    {
        $this->authorize('create', JournalEntry::class);
        $entry = $this->service->createManual($request->validated());

        return redirect()->route('accounting.journals.show', $entry)->with('success', 'Journal created.');
    }

    public function show(JournalEntry $journalEntry): View
    {
        $this->authorize('view', $journalEntry);

        return view('accounting.journals.show', ['entry' => $journalEntry->load('lines.account')]);
    }

    public function edit(JournalEntry $journalEntry): View
    {
        $this->authorize('update', $journalEntry);

        return view('accounting.journals.form', $this->formData($journalEntry->load('lines')));
    }

    public function update(JournalEntryRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('update', $journalEntry);
        $entry = $this->service->updateManual($journalEntry, $request->validated());

        return redirect()->route('accounting.journals.show', $entry)->with('success', 'Journal updated.');
    }

    private function formData(JournalEntry $entry): array
    {
        return [
            'entry' => $entry,
            'accounts' => Account::query()->where('company_id', $this->tenant->companyId())
                ->where('is_active', true)->where('is_posting', true)->orderBy('account_code')->get(),
            'currencies' => Currency::query()->where(fn ($query) => $query->whereNull('company_id')
                ->orWhere('company_id', $this->tenant->companyId()))->where('is_active', true)->get(),
        ];
    }
}
