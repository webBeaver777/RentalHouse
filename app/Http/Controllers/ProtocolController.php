<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * M10.1, Scenario A slice: check-in protocol creation entry point from
 * an existing property.
 *
 * Scope strictly: property -> draft check-in protocol -> draft show.
 * Backend (Protocol model, ProtocolType, state machine) is frozen and
 * already covered by ProtocolTest / ProtocolStateMachineTest /
 * AsymmetricStateMachineTest — this controller only wires UI + routes,
 * the same shape as PropertyController::store.
 *
 * NOT in this slice: rooms/items, photos, payment/gate, submit to
 * counterparty, signatures, finalize, PDF, magic-link (M10.2-M10.5).
 */
final class ProtocolController extends Controller
{
    public function create(Request $request): Response
    {
        $validated = $request->validate([
            'property_id' => ['required', 'integer'],
        ]);

        $property = Property::where('user_id', Auth::id())
            ->findOrFail($validated['property_id']);

        return Inertia::render('Protocol/Create', [
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'full_address' => $property->full_address,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $property = Property::where('user_id', Auth::id())
            ->findOrFail($validated['property_id']);

        $title = $validated['title'] ?? null;

        $protocol = Protocol::create([
            'property_id' => $property->id,
            'created_by_user_id' => Auth::id(),
            'type' => ProtocolType::CHECK_IN,
            'title' => $title !== null && $title !== ''
                ? $title
                : 'Protokół wjazdu — '.$property->name,
        ]);

        return redirect()->route('protocols.show', $protocol);
    }

    public function show(Protocol $protocol): Response
    {
        abort_unless($protocol->created_by_user_id === Auth::id(), 403);

        $protocol->loadMissing(['property', 'createdBy']);

        return Inertia::render('Protocol/Show', [
            'protocol' => [
                'id' => $protocol->id,
                'type' => $protocol->type->value,
                'type_label' => $protocol->type->label(),
                'status' => $protocol->status->value,
                'status_label' => $protocol->status->label(),
                'title' => $protocol->title,
                'property' => [
                    'name' => $protocol->property->name,
                    'full_address' => $protocol->property->full_address,
                ],
                'initiator_name' => $protocol->createdBy->name,
                'created_at' => $protocol->created_at?->toIso8601String(),
            ],
        ]);
    }
}
