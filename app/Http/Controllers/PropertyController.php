<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * M10 GATE 2, Scenario A slice: public-facing property management.
 *
 * Not to be confused with Filament's PropertyResource (admin panel).
 * This is the regular user's own "kabinet" view of their properties.
 */
final class PropertyController extends Controller
{
    public function index(): Response
    {
        $properties = Property::where('user_id', Auth::id())
            ->latest()
            ->get()
            ->map(fn (Property $property) => [
                'id' => $property->id,
                'name' => $property->name,
                'full_address' => $property->full_address,
                'declaration_type' => $property->declaration_type?->value,
                'declaration_type_label' => $property->declaration_type?->label(),
            ]);

        return Inertia::render('Properties/Index', [
            'properties' => $properties,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Properties/Create', [
            'declarationTypes' => array_map(
                fn (DeclarationType $type) => ['value' => $type->value, 'label' => $type->label()],
                DeclarationType::cases()
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'building_number' => ['required', 'string', 'max:20'],
            'apartment_number' => ['nullable', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'declaration_type' => ['required', Rule::enum(DeclarationType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'name' => 'nazwa',
            'street' => 'ulica',
            'building_number' => 'numer budynku',
            'apartment_number' => 'numer mieszkania',
            'city' => 'miasto',
            'postal_code' => 'kod pocztowy',
            'declaration_type' => 'typ zgłoszenia',
        ]);

        Property::create([
            ...$validated,
            'user_id' => Auth::id(),
            'country' => $validated['country'] ?? 'PL',
        ]);

        return redirect()->route('properties.index')
            ->with('status', 'Obiekt został dodany.');
    }
}
