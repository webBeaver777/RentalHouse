<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Billing\Application\Services\EntitlementService;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
 * M10.4: it now goes through EntitlementService::createDevEntitlement(),
 * the SAME emitter createEntitlementFromPayment() uses — previously this
 * duplicated the Entitlement::create() call inline, which would have let
 * dev and prod entitlement shapes drift. Replace this whole method with a
 * real Payment-driven call once the P24 slice lands; devGrant is 404'd
 * outside non-production environments as a guardrail until then.
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

    public function devGrant(Request $request, EntitlementService $entitlementService): RedirectResponse
    {
        abort_if(app()->environment('production'), 404);

        $validated = $request->validate([
            'product_code' => [
                'required',
                Rule::in(array_map(fn (ProductCode $code) => $code->value, ProductCode::mvpProducts())),
            ],
            // Optional: bounce back to the protocol that requested the payment
            // (used by the "Zapłać (dev)" button on Protocol/Show.vue) instead
            // of the billing index. Built into a route URL server-side —
            // never redirects to an arbitrary external URL.
            'protocol_id' => ['nullable', 'uuid'],
        ]);

        $productCode = ProductCode::from($validated['product_code']);

        /** @var User $user */
        $user = $request->user();

        $entitlementService->createDevEntitlement($user, $productCode);

        $redirectTo = isset($validated['protocol_id'])
            ? route('protocols.show', $validated['protocol_id'])
            : route('billing.index');

        return redirect($redirectTo)
            ->with('status', 'Dostęp (dev) przyznany: '.$productCode->label());
    }
}
