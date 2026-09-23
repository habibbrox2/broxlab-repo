<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\HaCashRegisterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cash register open/close + session detail.
 */
class HaRegisterController extends Controller
{
    public function __construct(
        protected HaCashRegisterService $registers,
    ) {}

    public function index(): View
    {
        return view('admin.ha.registers', [
            'current' => $this->registers->current(),
            'registers' => \App\Models\HaCashRegister::query()->with('transactions')->orderByDesc('id')->paginate(20),
            'summary' => $this->registers->current() ? $this->registers->summary($this->registers->current()) : null,
        ]);
    }

    public function open(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->registers->open((int) auth()->id(), (float) $data['opening_balance'], $data['note'] ?? null);
        } catch (\Throwable $e) {
            return redirect('/admin/ha/register')->with('error', $e->getMessage());
        }

        return redirect('/admin/ha/register')->with('status', 'Register opened');
    }

    public function close(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $current = $this->registers->current();
        if (! $current) {
            return redirect('/admin/ha/register')->with('error', 'No open register session.');
        }

        $this->registers->close($current, (float) $data['actual_cash'], $data['note'] ?? null, (int) auth()->id());

        return redirect('/admin/ha/register')->with('status', 'Register closed');
    }

    public function cashMovement(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'type' => ['required', 'in:cash_in,cash_out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['required', 'string', 'max:255'],
        ]);

        $current = $this->registers->current();
        if (! $current) {
            return redirect('/admin/ha/register')->with('error', 'No open register session.');
        }

        $this->registers->recordTransaction($current, $data['type'], (float) $data['amount'], 'manual', null, $data['note'], (int) auth()->id());

        return redirect('/admin/ha/register')->with('status', 'Cash movement recorded');
    }
}
