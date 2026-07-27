<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Domain\Enums;

/**
 * D7: Full set of inspection event types (§15).
 */
enum InspectionEventType: string
{
    // Protocol lifecycle
    case PROTOCOL_CREATED = 'protocol_created';
    case PROTOCOL_UPDATED = 'protocol_updated';
    case PROTOCOL_SUBMITTED = 'protocol_submitted';
    case PROTOCOL_FINALIZED = 'protocol_finalized';
    case PROTOCOL_CANCELLED = 'protocol_cancelled';
    case PROTOCOL_STATUS_CHANGED = 'protocol_status_changed'; // D8: Generic status change
    case PROTOCOL_ARCHIVED = 'protocol_archived'; // D8: Access expired
    case PROTOCOL_PURGED = 'protocol_purged'; // D8: Retention expired

    // Invitation events
    case INVITATION_SENT = 'invitation_sent';
    case INVITATION_VIEWED = 'invitation_viewed';
    case INVITATION_EXPIRED = 'invitation_expired';
    case INVITATION_RESENT = 'invitation_resent';

    // Participation events
    case PARTICIPANT_JOINED = 'participant_joined';
    case PARTICIPANT_LEFT = 'participant_left';

    // Content events
    case ROOM_ADDED = 'room_added';
    case ROOM_UPDATED = 'room_updated';
    case ROOM_REMOVED = 'room_removed';
    case ITEM_ADDED = 'item_added';
    case ITEM_UPDATED = 'item_updated';
    case ITEM_REMOVED = 'item_removed';
    case PHOTO_UPLOADED = 'photo_uploaded';
    case PHOTO_REMOVED = 'photo_removed';
    case METER_READING_ADDED = 'meter_reading_added';
    case METER_READING_UPDATED = 'meter_reading_updated';
    case KEY_ADDED = 'key_added';
    case KEY_UPDATED = 'key_updated';
    case DEFECT_REPORTED = 'defect_reported';
    case DEFECT_UPDATED = 'defect_updated';
    case DEFECT_RESOLVED = 'defect_resolved';

    // Counterparty events (D6)
    case COMMENT_ADDED = 'comment_added';
    case COUNTERPARTY_PHOTO_UPLOADED = 'counterparty_photo_uploaded';

    // Signature events
    case SIGNATURE_REQUESTED = 'signature_requested';
    case SIGNATURE_ADDED = 'signature_added';
    case SIGNATURE_DECLINED = 'signature_declined';

    // Check-out specific
    case ACT_ISSUED = 'act_issued';
    case OBJECTION_WINDOW_OPENED = 'objection_window_opened';
    case OBJECTION_WINDOW_CLOSING = 'objection_window_closing';
    case OBJECTION_WINDOW_CLOSED = 'objection_window_closed';
    case OBJECTION_RAISED = 'objection_raised';
    case OBJECTION_RESOLVED = 'objection_resolved';

    // Document events
    case PDF_GENERATION_STARTED = 'pdf_generation_started';
    case PDF_GENERATION_COMPLETED = 'pdf_generation_completed';
    case PDF_GENERATION_FAILED = 'pdf_generation_failed';
    case DOCUMENT_DOWNLOADED = 'document_downloaded';

    // Payment events (D1)
    case PAYMENT_STARTED = 'payment_started';
    case PAYMENT_PAID = 'payment_paid';
    case PAYMENT_FAILED = 'payment_failed';
    case ENTITLEMENT_CONSUMED = 'entitlement_consumed';

    // Access events
    case MAGIC_LINK_USED = 'magic_link_used';
    case ACCESS_GRANTED = 'access_granted';
    case ACCESS_EXPIRED = 'access_expired';

    public function label(): string
    {
        return match ($this) {
            self::PROTOCOL_CREATED => 'Protokół utworzony',
            self::PROTOCOL_UPDATED => 'Protokół zaktualizowany',
            self::PROTOCOL_SUBMITTED => 'Protokół przesłany',
            self::PROTOCOL_FINALIZED => 'Protokół sfinalizowany',
            self::PROTOCOL_CANCELLED => 'Protokół anulowany',
            self::PROTOCOL_STATUS_CHANGED => 'Zmiana statusu protokołu',
            self::PROTOCOL_ARCHIVED => 'Protokół zarchiwizowany',
            self::PROTOCOL_PURGED => 'Protokół usunięty',
            self::INVITATION_SENT => 'Zaproszenie wysłane',
            self::INVITATION_VIEWED => 'Zaproszenie wyświetlone',
            self::INVITATION_EXPIRED => 'Zaproszenie wygasło',
            self::INVITATION_RESENT => 'Zaproszenie wysłane ponownie',
            self::PARTICIPANT_JOINED => 'Uczestnik dołączył',
            self::PARTICIPANT_LEFT => 'Uczestnik opuścił',
            self::ROOM_ADDED => 'Dodano pomieszczenie',
            self::ROOM_UPDATED => 'Zaktualizowano pomieszczenie',
            self::ROOM_REMOVED => 'Usunięto pomieszczenie',
            self::ITEM_ADDED => 'Dodano element',
            self::ITEM_UPDATED => 'Zaktualizowano element',
            self::ITEM_REMOVED => 'Usunięto element',
            self::PHOTO_UPLOADED => 'Przesłano zdjęcie',
            self::PHOTO_REMOVED => 'Usunięto zdjęcie',
            self::METER_READING_ADDED => 'Dodano odczyt licznika',
            self::METER_READING_UPDATED => 'Zaktualizowano odczyt licznika',
            self::KEY_ADDED => 'Dodano klucz',
            self::KEY_UPDATED => 'Zaktualizowano klucz',
            self::DEFECT_REPORTED => 'Zgłoszono usterkę',
            self::DEFECT_UPDATED => 'Zaktualizowano usterkę',
            self::DEFECT_RESOLVED => 'Rozwiązano usterkę',
            self::COMMENT_ADDED => 'Dodano komentarz',
            self::COUNTERPARTY_PHOTO_UPLOADED => 'Druga strona przesłała zdjęcie',
            self::SIGNATURE_REQUESTED => 'Poproszono o podpis',
            self::SIGNATURE_ADDED => 'Dodano podpis',
            self::SIGNATURE_DECLINED => 'Odmówiono podpisu',
            self::ACT_ISSUED => 'Akt wydany',
            self::OBJECTION_WINDOW_OPENED => 'Otwarto okno zastrzeżeń',
            self::OBJECTION_WINDOW_CLOSING => 'Okno zastrzeżeń zamyka się',
            self::OBJECTION_WINDOW_CLOSED => 'Okno zastrzeżeń zamknięte',
            self::OBJECTION_RAISED => 'Zgłoszono zastrzeżenie',
            self::OBJECTION_RESOLVED => 'Rozwiązano zastrzeżenie',
            self::PDF_GENERATION_STARTED => 'Rozpoczęto generowanie PDF',
            self::PDF_GENERATION_COMPLETED => 'Wygenerowano PDF',
            self::PDF_GENERATION_FAILED => 'Błąd generowania PDF',
            self::DOCUMENT_DOWNLOADED => 'Pobrano dokument',
            self::PAYMENT_STARTED => 'Rozpoczęto płatność',
            self::PAYMENT_PAID => 'Płatność opłacona',
            self::PAYMENT_FAILED => 'Płatność nieudana',
            self::ENTITLEMENT_CONSUMED => 'Wykorzystano uprawnienie',
            self::MAGIC_LINK_USED => 'Użyto linku zaproszenia',
            self::ACCESS_GRANTED => 'Udzielono dostępu',
            self::ACCESS_EXPIRED => 'Dostęp wygasł',
        };
    }

    /**
     * Get short version for PDF timeline.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::PROTOCOL_CREATED => 'Utworzono',
            self::PROTOCOL_SUBMITTED => 'Przesłano',
            self::PROTOCOL_FINALIZED => 'Sfinalizowano',
            self::INVITATION_SENT => 'Zaproszono',
            self::SIGNATURE_ADDED => 'Podpisano',
            self::ACT_ISSUED => 'Wydano akt',
            self::OBJECTION_RAISED => 'Zastrzeżenie',
            self::PAYMENT_PAID => 'Opłacono',
            default => $this->label(),
        };
    }
}
