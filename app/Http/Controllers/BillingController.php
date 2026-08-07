<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Billing\Application\Services\EntitlementService;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Entitlement;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * M10 GATE 2: entitlements/access screen.
 *
 * D3 HARD-GATE: sending a check-in invitation or issuing a check-out act
 * requires a consumed entitlement — no free plan (see EntitlementService).
 *
 * devGrant() is a TEMPORARY stand-in for the real Przelewy24 purchase flow.
 * routes/web.php still only has unwired /payment/sandbox and /payment/return
 * placeholders — no controller creates a Payment record yet. This lets
 * Scenario A be exercised end-to-end in dev without a live P24 sandbox.
 * Replace with EntitlementService::createEntitlementFromPayment() driven by
 * a real Payment once the P24 slice lands; devGrant is 404'd outside
 * non-production environments as a guardrail until then.
 */
final class BillingController extends Controller
{
    public function index(Request $request, EntitlementService $entitlementService): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Billing/Entitlements', [
            'entitlements' => $entitlementService->getUserEntitlements($user),
            'products' => array_map(
                fn (ProductCode $code) => ['code' => $code->value, 'label' => $code->label()],
                ProductCode::mvpProducts()
            ),
            'devModeAvailable' => ! app()->environment('production'),
        ]);
    }

    public function devGrant(Request $request): RedirectResponse
    {
        abort_if(app()->environment('production'), 404);

        $validated = $request->validate([
            'product_code' => [
                'required',
                Rule::in(array_map(fn (ProductCode $code) => $code->value, ProductCode::mvpProducts())),
            ],
        ]);

        $productCode = ProductCode::from($validated['product_code']);

        Entitlement::create([
            'user_id' => Auth::id(),
            'product_code' => $productCode,
            'allowed_action' => $productCode->allowedAction(),
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);

        return redirect()->route('billing.index')
            ->with('status', 'Dostęp (dev) przyznany: '.$productCode->label());
    }
}
