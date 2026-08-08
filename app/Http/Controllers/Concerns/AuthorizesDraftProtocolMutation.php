<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Support\Facades\Auth;

/**
 * M10.2 KRYTYCZNY GUARD: room/item structure on a protocol may only be
 * mutated while it is still draft, and only by its initiator. M10.4 reuses
 * this same draft-only + initiator check for submitting to the counterparty
 * (with a submit-specific message).
 *
 * This is the backend enforcement of the immutability boundary — hiding
 * buttons in the UI is not enough, every mutating controller action must
 * reject explicitly (403 wrong user, 422 non-draft).
 */
trait AuthorizesDraftProtocolMutation
{
    protected function assertMutable(Protocol $protocol, ?string $message = null): void
    {
        abort_unless($protocol->created_by_user_id === Auth::id(), 403);

        abort_unless(
            $protocol->status === ProtocolStatus::DRAFT,
            422,
            $message ?? 'Struktura protokołu może być zmieniana tylko w statusie „Szkic”.'
        );
    }
}
