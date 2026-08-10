<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesDraftProtocolMutation;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * M13 step 1, Scenario C slice 2: let the check-out initiator record the
 * deposit amount on their draft act. Pure wiring over an existing column
 * (Protocol::deposit_amount, already fillable/cast — see
 * ScenarioCTest::test_scenario_c_deposit_amount_tracked and
 * Protocol::getAmountToReturnAttribute()) — no new model/field invented.
 *
 * Draft-only + initiator guard, same as room/item mutation (see
 * AuthorizesDraftProtocolMutation) — once the act is issued the protocol is
 * COMPLETED and this rejects with 422, keeping the sealed act immutable.
 */
final class ProtocolDepositController extends Controller
{
    use AuthorizesDraftProtocolMutation;

    public function update(Request $request, Protocol $protocol): RedirectResponse
    {
        $this->assertMutable($protocol, 'Kwotę kaucji można ustawić tylko w statusie „Szkic”.');
        abort_unless($protocol->type === ProtocolType::CHECK_OUT, 404);

        $validated = $request->validate([
            'deposit_amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $protocol->update(['deposit_amount' => $validated['deposit_amount']]);

        return redirect()->route('protocols.show', $protocol)
            ->with('status', 'Kwota kaucji została zapisana.');
    }
}
