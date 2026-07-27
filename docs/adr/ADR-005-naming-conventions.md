# ADR-005: Konwencje nazewnictwa

## Status

Przyjęte

## Kontekst

W projekcie pojawiły się rozbieżności między terminologią z ТЗ MVP a rzeczywistymi nazwami w kodzie. Konieczne jest ustalenie kanonicznych nazw dla kluczowych pojęć, aby uniknąć nieporozumień i ułatwić onboarding nowych programistów.

## Decyzja

### Kanoniczne nazwy tabel i modeli

| Pojęcie w ТЗ | Tabela w DB | Model PHP | Uzasadnienie |
|--------------|-------------|-----------|--------------|
| Inspection / Protokół | `protocols` | `Protocol` | "Protocol" lepiej oddaje charakter dokumentu zdawczo-odbiorczego |
| Counterparty participation | `participants` | `Participant` | Krótsza, bardziej uniwersalna nazwa |
| Inspection timeline | `inspection_events` | `InspectionEvent` | Zachowujemy "inspection" dla historycznych zdarzeń |
| Magic link | `invitation_tokens` | `InvitationToken` | Precyzyjniejsza nazwa techniczna |
| Property declaration type | `declaration_type` | `DeclarationType` enum | W `properties`, nie w `users` |

### Konwencje nazewnictwa

1. **Tabele**: snake_case, liczba mnoga (`protocols`, `participants`)
2. **Modele**: PascalCase, liczba pojedyncza (`Protocol`, `Participant`)
3. **Enumy**: PascalCase (`ProtocolStatus`, `ParticipantRole`)
4. **Akcje**: `{Verb}{Noun}Action` (`FinalizeCheckInAction`, `SignProtocolAction`)
5. **Serwisy**: `{Noun}Service` (`LifecycleService`, `InvitationService`)
6. **Wyjątki**: `{Noun}Exception` (`EntitlementRequiredException`)

### Prefiksy i sufiksy

| Typ | Wzorzec | Przykład |
|-----|---------|----------|
| Akcja | `{Verb}{Noun}Action` | `IssueCheckOutAction` |
| Serwis | `{Noun}Service` | `PdfGenerationService` |
| Job | `{Verb}{Noun}` | `CompleteExpiredObjectionWindows` |
| Event | `{Noun}{Past}` | `ParticipantInvited` |
| Listener | `{Verb}{Noun}` | `SendInvitationNotification` |
| Notification | `{Noun}Notification` | `InvitationNotification` |
| Request | `{Verb}{Noun}Request` | `CreateProtocolRequest` |

### Enumy statusów

Nazwy wartości enum używają snake_case:

```php
enum ProtocolStatus: string {
    case DRAFT = 'draft';
    case PENDING_COUNTERPARTY = 'pending_counterparty';
    case PENDING_SIGNATURES = 'pending_signatures';
    case SIGNED = 'signed';
    case COMPLETED = 'completed';
    case ARCHIVED = 'archived';
    case CANCELLED = 'cancelled';
}
```

### Pola timestampów

| Pole | Konwencja |
|------|-----------|
| Data wydarzenia | `{noun}_at` (`completed_at`, `signed_at`) |
| Data początku | `{noun}_started_at` (`objection_window_started_at`) |
| Data końca | `{noun}_ends_at` (`objection_window_ends_at`) |
| Data ważności | `{noun}_until` (`retention_until`, `valid_until`) |

### Pola flagowe

Booleany używają prefiksu `is_`:

```php
$table->boolean('is_initiator')->default(false);
$table->boolean('is_one_time')->default(false);
$table->boolean('is_admin')->default(false);
```

### Relacje

| Typ relacji | Nazwa metody |
|-------------|--------------|
| BelongsTo | nazwa w l.poj (`property()`, `user()`) |
| HasMany | nazwa w l.mn (`rooms()`, `participants()`) |
| HasOne | nazwa w l.poj (`initiator()`) |

### Scope'y Eloquent

Używamy nazw opisowych bez prefiksu:

```php
// Dobrze
Protocol::completed()->...
Participant::signed()->...
Entitlement::valid()->forAction($action)->...

// Źle (nie używać)
Protocol::scopeCompleted()->...
```

## Konsekwencje

### Pozytywne

- Spójne nazewnictwo w całej aplikacji
- Łatwiejszy onboarding nowych programistów
- Mniejsze ryzyko pomyłek przy code review

### Negatywne

- Różnice między ТЗ a kodem wymagają dokumentacji (D10)
- Możliwe konflikty z zewnętrznymi bibliotekami

## Powiązania

- [D10-FIELD-MAPPING.md](../D10-FIELD-MAPPING.md) - Szczegółowa tabela mapowania pól
- [ARCHITECTURE.md](../ARCHITECTURE.md) - Architektura modułowa
