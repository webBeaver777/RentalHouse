# D10: Tabela Odpowiedniości Pól (Field Mapping Table)

> Mapowanie pól z ТЗ MVP §13.1 na pola w kodzie.

## Kluczowe ustalenia nazewnicze

| Pojęcie w ТЗ | Nazwa w kodzie | Uwagi |
|--------------|---------------|-------|
| `inspection` | `protocol` | Tabela: `protocols`. Model: `Protocol` |
| `counterparty_participation` | `participant` | Tabela: `participants`. Model: `Participant` |
| `inspection_timeline` | `inspection_events` | Tabela: `inspection_events`. Model: `InspectionEvent` |
| `magic_link` | `invitation_token` | Tabela: `invitation_tokens`. Model: `InvitationToken` |

---

## Protokoły (`protocols`)

### Pola podstawowe

| Pole ТЗ §13.1 | Pole w kodzie | Typ | Opis |
|---------------|---------------|-----|------|
| `id` | `id` | UUID | Klucz główny |
| `property_id` | `property_id` | FK | Odniesienie do nieruchomości |
| `created_by` | `created_by_user_id` | FK | Użytkownik tworzący protokół |
| `type` | `type` | enum | `check_in`, `check_out`, `periodic` |
| `status` | `status` | enum | Stan protokołu (FSM) |
| `title` | `title` | string | Tytuł protokołu |
| `description` | `description` | text | Opcjonalny opis |
| `scheduled_at` | `scheduled_at` | timestamp | Planowana data |
| `completed_at` | `completed_at` | timestamp | Data zakończenia |
| `metadata` | `metadata` | jsonb | Dane dodatkowe |

### Pola asymetryczne (P1.0)

| Pole ТЗ §13.1 | Pole w kodzie | Typ | Opis |
|---------------|---------------|-----|------|
| `initiator_role` | `initiator_role` | enum | `landlord` lub `tenant` - kto zainicjował |
| `counterparty_role` | `counterparty_role` | enum | `landlord` lub `tenant` - druga strona |
| `legal_mode` | `legal_mode` | enum | `standard`, `unilateral_landlord`, `unilateral_tenant` |
| `act_issued_at` | `act_issued_at` | timestamp | Data wystawienia aktu (check-out) |
| `objection_window_ends_at` | `objection_window_ends_at` | timestamp | Koniec okna sprzeciwu |
| `reference_mode` | `reference_mode` | enum | `system_baseline`, `uploaded_reference`, `no_reference` |
| `linked_checkin_id` | `linked_checkin_id` | UUID FK | Powiązanie z check-in (dla check-out) |
| `locale` | `locale` | string(5) | Język protokołu (domyślnie `pl`) |
| `property_declaration_type` | `property_declaration_type` | enum | Snapshot typu deklaracji nieruchomości |
| `deposit_amount` | `deposit_amount` | decimal(10,2) | Kwota kaucji |

### Pola dokumentu (D5)

| Pole ТЗ | Pole w kodzie | Typ | Opis |
|---------|---------------|-----|------|
| `document_hash` | `document_hash` | string | SHA-256 hash PDF (zamrożony przy pierwszej generacji) |

### Pola lifecycle (D8)

| Pole ТЗ | Pole w kodzie | Typ | Opis |
|---------|---------------|-----|------|
| `paid_at` | `paid_at` | timestamp | Data opłacenia |
| `access_expires_at` | `access_expires_at` | timestamp | `paid_at + 12 miesięcy` (konfigurowalny) |
| `retention_until` | `retention_until` | timestamp | `access_expires_at + 3 lata` (RODO) |

### Pola z §13.1, których NIE ma w schemacie (M8-VERIFY, uczciwie nie ukryte)

| Pole ТЗ §13.1 | Status | Uwaga |
|---------------|--------|-------|
| `initiator_user_id` | ⚠️ brak kolumny | Inicjator wyrażony pośrednio: `Participant.is_initiator = true` + `Participant.user_id`. Brak osobnej kolumny na `protocols` |
| `counterparty_email` | ⚠️ brak kolumny | Najbliższy odpowiednik: `Participant.invited_email` (inna nazwa/semantyka) |
| `counterparty_phone` | ❌ brak w ogóle | Nie znaleziono ani w `protocols`, ani w `participants` — wymaga migracji, jeśli pole jest faktycznie wymagane |
| `total_damage_cost` | ⚠️ computed, nie kolumna | `Protocol::getTotalDamageCostAttribute()` — suma `estimated_cost` z `protocol_defects`, liczona w locie, nie zapisywana |
| `amount_to_return` | ⚠️ computed, nie kolumna | `Protocol::getAmountToReturnAttribute()` — `deposit_amount - total_damage_cost`, nie zapisywana |
| `pdf_status` | ❌ brak w ogóle | PDF generowany synchronicznie (`PdfGenerationService`), bez kolejki i bez pola statusu — dług Fazy 5 M8 |

---

## Akceptacje pozycji (`item_acceptances`) — encja odrębna od `acceptances`

`item_acceptances` i `acceptances` to **dwie różne encje**, nie duplikat:

- `acceptances` — jeden wiersz na podpis/zgodę całego protokołu przez uczestnika (forensyka: `consent_text_snapshot`, `ip_address`, `user_agent`, `device_fingerprint`). Patrz sekcja niżej.
- `item_acceptances` — jeden wiersz na akceptację/spór **pojedynczej pozycji** (`protocol_items`) przez uczestnika: `status` (`pending`/`accepted`/`disputed`), `dispute_reason`, `resolution_notes`. Używane przy szczegółowej weryfikacji stanu poszczególnych elementów, niezależnie od ogólnego podpisu protokołu.

---

## Uczestnicy (`participants`)

| Pole ТЗ | Pole w kodzie | Typ | Opis |
|---------|---------------|-----|------|
| `id` | `id` | bigint | Klucz główny |
| `protocol_id` | `protocol_id` | UUID FK | Protokół |
| `user_id` | `user_id` | FK nullable | Użytkownik (jeśli zarejestrowany) |
| `role` | `role` | enum | `landlord`, `tenant`, `witness` |
| `is_initiator` | `is_initiator` | boolean | Czy zainicjował protokół |
| `invited_email` | `invited_email` | string | Email zaproszenia |
| `invited_at` | `invited_at` | timestamp | Data zaproszenia |
| `accepted_at` | `accepted_at` | timestamp | Data akceptacji zaproszenia |
| `signed_at` | `signed_at` | timestamp | Data podpisania |
| `signature_data` | `signature_data` | text | Dane podpisu (base64) |
| `metadata` | `metadata` | jsonb | Dane dodatkowe |

### Wyliczane atrybuty

| Atrybut | Opis |
|---------|------|
| `participation_status` | `pending`, `sent`, `opened`, `commented`, `accepted`, `signed` |
| `participation_status_label` | Etykieta po polsku |

---

## Tokeny zaproszeń (`invitation_tokens`)

| Pole ТЗ (G3) | Pole w kodzie | Typ | Opis |
|--------------|---------------|-----|------|
| `id` | `id` | bigint | Klucz główny |
| `participant_id` | `participant_id` | FK | Uczestnik |
| `token_hash` | `token_hash` | string | SHA-256 tokena (nie raw!) |
| `expires_at` | `expires_at` | timestamp | Data wygaśnięcia (72h z konfiga) |
| `is_one_time` | `is_one_time` | boolean | Czy jednorazowy |
| `used_at` | `used_at` | timestamp | Data wykorzystania |
| `revoked_at` | `revoked_at` | timestamp | Data odwołania |

---

## Akceptacje (`acceptances`)

| Pole ТЗ (G2) | Pole w kodzie | Typ | Opis |
|--------------|---------------|-----|------|
| `id` | `id` | bigint | Klucz główny |
| `protocol_id` | `protocol_id` | UUID FK | Protokół |
| `participant_id` | `participant_id` | FK | Uczestnik |
| `accepted_by_role` | `accepted_by_role` | enum | Rola akceptującego |
| `accepted_at` | `accepted_at` | timestamp | Data akceptacji |
| `consent_text_snapshot` | `consent_text_snapshot` | text | Zamrożony tekst zgody |
| `ip_address` | `ip_address` | string | Adres IP |
| `user_agent` | `user_agent` | string | User agent |
| `device_fingerprint` | `device_fingerprint` | string | Fingerprint urządzenia |

---

## Zdarzenia inspekcji (`inspection_events`)

| Pole ТЗ | Pole w kodzie | Typ | Opis |
|---------|---------------|-----|------|
| `id` | `id` | bigint | Klucz główny |
| `protocol_id` | `protocol_id` | UUID FK | Protokół |
| `event_type` | `event_type` | enum | Typ zdarzenia |
| `actor_role` | `actor_role` | string | Rola aktora (`landlord`, `tenant`, `system`) |
| `actor_user_id` | `actor_user_id` | FK nullable | Użytkownik-aktor |
| `payload` | `payload` | jsonb | Dane zdarzenia |
| `ip_address` | `ip_address` | string | Adres IP |
| `user_agent` | `user_agent` | string | User agent |
| `created_at` | `created_at` | timestamp | Czas zdarzenia |

### Typy zdarzeń (`InspectionEventType`)

| Wartość | Opis |
|---------|------|
| `protocol_created` | Utworzenie protokołu |
| `protocol_status_changed` | Zmiana statusu |
| `invitation_sent` | Wysłanie zaproszenia |
| `magic_link_used` | Użycie magic-link |
| `participant_accepted` | Akceptacja uczestnictwa |
| `signature_added` | Dodanie podpisu |
| `comment_added` | Dodanie komentarza |
| `photo_uploaded` | Przesłanie zdjęcia |
| `objection_raised` | Zgłoszenie sprzeciwu |
| `objection_resolved` | Rozwiązanie sprzeciwu |
| `document_generated` | Generacja dokumentu |
| `protocol_finalized` | Finalizacja protokołu |

---

## Dokumenty referencyjne (`reference_documents`)

| Pole ТЗ (G4) | Pole w kodzie | Typ | Opis |
|--------------|---------------|-----|------|
| `id` | `id` | bigint | Klucz główny |
| `protocol_id` | `protocol_id` | UUID FK | Protokół |
| `type` | `type` | enum | `pdf`, `photo`, `paper_protocol`, `lease_contract`, `other` |
| `file_path` | `file_path` | string | Ścieżka w storage |
| `file_hash` | `file_hash` | string | SHA-256 pliku |
| `filename` | `filename` | string | Oryginalna nazwa pliku |
| `mime_type` | `mime_type` | string | Typ MIME |
| `size` | `size` | bigint | Rozmiar w bajtach |
| `note` | `note` | text | Notatka |
| `uploaded_at` | `uploaded_at` | timestamp | Data przesłania |

---

## Pomieszczenia (`protocol_rooms`)

| Pole | Typ | Opis |
|------|-----|------|
| `id` | bigint | Klucz główny |
| `protocol_id` | UUID FK | Protokół |
| `catalog_room_id` | FK nullable | Odniesienie do katalogu |
| `name` | string | Nazwa (z katalogu lub własna) |
| `name_snapshot` | string | Zamrożona nazwa (niezmienność) |
| `sort_order` | int | Kolejność |

---

## Elementy (`protocol_items`)

| Pole | Typ | Opis |
|------|-----|------|
| `id` | bigint | Klucz główny |
| `room_id` | FK | Pomieszczenie |
| `catalog_item_id` | FK nullable | Odniesienie do katalogu |
| `name` | string | Nazwa elementu |
| `condition` | enum | Stan (`new`, `good`, `fair`, `poor`, `damaged`) |
| `quantity` | int | Ilość |
| `notes` | text | Uwagi |
| `features` | jsonb | Cechy dodatkowe |
| `sort_order` | int | Kolejność |

---

## Liczniki (`protocol_meters`)

| Pole | Typ | Opis |
|------|-----|------|
| `id` | bigint | Klucz główny |
| `protocol_id` | UUID FK | Protokół |
| `type` | enum | `electricity`, `gas`, `water_cold`, `water_hot`, `heating` |
| `meter_number` | string | Numer licznika |
| `reading` | decimal | Odczyt |
| `unit` | string | Jednostka |
| `photo_path` | string | Zdjęcie licznika |
| `sort_order` | int | Kolejność |

---

## Klucze (`protocol_keys`)

| Pole | Typ | Opis |
|------|-----|------|
| `id` | bigint | Klucz główny |
| `protocol_id` | UUID FK | Protokół |
| `description` | string | Opis klucza |
| `quantity` | int | Ilość |
| `sort_order` | int | Kolejność |

---

## Usterki (`protocol_defects`)

| Pole | Typ | Opis |
|------|-----|------|
| `id` | bigint | Klucz główny |
| `protocol_id` | UUID FK | Protokół |
| `room_id` | FK nullable | Pomieszczenie |
| `item_id` | FK nullable | Element |
| `description` | text | Opis usterki |
| `severity` | enum | `minor`, `moderate`, `major`, `critical` |
| `estimated_cost` | decimal | Szacowany koszt naprawy |
| `photo_path` | string | Zdjęcie usterki |
| `sort_order` | int | Kolejność |

---

## Sprzeciwy (`protocol_objections`)

| Pole | Typ | Opis |
|------|-----|------|
| `id` | bigint | Klucz główny |
| `protocol_id` | UUID FK | Protokół |
| `participant_id` | FK | Uczestnik zgłaszający |
| `objection_text` | text | Treść sprzeciwu |
| `item_id` | FK nullable | Dotyczący elementu |
| `status` | enum | `pending`, `resolved`, `rejected` |
| `response_text` | text | Odpowiedź |
| `responded_at` | timestamp | Data odpowiedzi |
| `created_at` | timestamp | Data zgłoszenia |

---

## Komentarze (`protocol_comments`)

| Pole | Typ | Opis |
|------|-----|------|
| `id` | bigint | Klucz główny |
| `protocol_id` | UUID FK | Protokół |
| `participant_id` | FK | Uczestnik |
| `item_id` | FK nullable | Dotyczący elementu |
| `comment` | text | Treść komentarza |
| `created_at` | timestamp | Data utworzenia |

---

## Zdjęcia drugiej strony (`counterparty_photos`)

| Pole | Typ | Opis |
|------|-----|------|
| `id` | bigint | Klucz główny |
| `protocol_id` | UUID FK | Protokół |
| `participant_id` | FK | Uczestnik |
| `item_id` | FK nullable | Dotyczący elementu |
| `file_path` | string | Ścieżka w MinIO |
| `file_hash` | string | SHA-256 pliku |
| `caption` | string | Podpis |
| `created_at` | timestamp | Data przesłania |

---

## Enumeracje kluczowe

### `ProtocolStatus` (Stany FSM)

| Wartość | Opis |
|---------|------|
| `draft` | Szkic - edytowalny |
| `pending_counterparty` | Oczekuje na drugą stronę |
| `pending_signatures` | Oczekuje na podpisy |
| `signed` | Podpisany przez wszystkich |
| `completed` | Zakończony |
| `archived` | Zarchiwizowany (po wygaśnięciu dostępu) |
| `cancelled` | Anulowany |

### `ProtocolType`

| Wartość | Opis |
|---------|------|
| `check_in` | Protokół wjazdowy |
| `check_out` | Protokół wyjazdowy |
| `periodic` | Protokół okresowy |

### `ParticipantRole`

| Wartość | Opis |
|---------|------|
| `landlord` | Wynajmujący |
| `tenant` | Najemca |
| `witness` | Świadek |

### `LegalMode`

| Wartość | Opis |
|---------|------|
| `standard` | Obie strony podpisują |
| `unilateral_landlord` | Tylko wynajmujący |
| `unilateral_tenant` | Tylko najemca |

### `ReferenceMode` (dla check-out)

| Wartość | Opis |
|---------|------|
| `system_baseline` | Bazowy check-in z systemu |
| `uploaded_reference` | Wgrany dokument referencyjny |
| `no_reference` | Bez bazy odniesienia |

---

## Relacje między tabelami

```
users ─┐
       │──< properties ──< protocols ──< protocol_rooms ──< protocol_items
       │                      │                                  │
       │                      ├──< participants ──< invitation_tokens
       │                      │         │
       │                      │         └──< acceptances
       │                      │
       │                      ├──< inspection_events
       │                      │
       │                      ├──< protocol_meters
       │                      ├──< protocol_keys
       │                      ├──< protocol_defects
       │                      ├──< protocol_comments
       │                      ├──< counterparty_photos
       │                      ├──< reference_documents
       │                      ├──< protocol_objections
       │                      │
       │                      └──< generated_documents
       │
       └──< entitlements ──< entitlement_usages
       └──< payments
```

---

## Uwagi implementacyjne

1. **UUID dla protokołów**: `protocols.id` używa UUID dla bezpieczeństwa linków publicznych
2. **Soft delete**: `protocols` używają soft delete dla retencji RODO
3. **Snapshot nazw**: `protocol_rooms.name_snapshot` zamraża nazwę przy tworzeniu
4. **Hash dokumentu**: `document_hash` liczony raz przy pierwszej generacji PDF
5. **Token hash**: `invitation_tokens.token_hash` przechowuje hash, nie raw token
