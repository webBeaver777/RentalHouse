# Rent2Proof / Etalon

Система для документирования состояния арендуемой недвижимости при заселении (check-in) и выселении (check-out).

## Описание проекта

**Rent2Proof** — это SaaS-приложение для создания юридически значимых протоколов приёма-передачи недвижимости. Система позволяет:

- Создавать протоколы заселения (check-in) и выселения (check-out)
- Фиксировать состояние помещений, счётчиков, ключей
- Загружать фотографии с автоматическим хешированием SHA-256
- Приглашать вторую сторону по email через magic-link
- Собирать электронные подписи обеих сторон
- Генерировать PDF-документы с QR-кодом для верификации
- Хранить документы с соблюдением сроков ретенции (GDPR/RODO)

### Ключевые особенности

- **Асимметричная модель**: Check-in требует подписи обеих сторон, check-out — только инициатора
- **HARD-GATE оплата**: PDF генерируется только после оплаты через Przelewy24
- **Заморозка данных**: После подписания протокол и его хеш неизменяемы
- **Верификация QR**: Публичная страница для проверки подлинности документа без раскрытия персональных данных
- **Мультиязычность**: Польский язык по умолчанию, поддержка локализации

## Технологический стек

| Компонент | Технология |
|-----------|------------|
| Backend | Laravel 13.8.x, PHP 8.4 |
| Frontend | Inertia.js v3, Vue 3, Vite, Tailwind CSS v4 |
| Админ-панель | Filament 5.6.x |
| База данных | PostgreSQL 17 |
| Кеш/Очереди | Redis 7 |
| Хранилище файлов | MinIO (S3-совместимое) |
| Почта (dev) | Mailpit |
| Оплата | Przelewy24 |

## Требования

- Docker и Docker Compose
- Make
- 4GB RAM минимум

## Быстрый старт

### 1. Клонирование репозитория

```bash
git clone https://github.com/your-repo/rent2proof.git
cd rent2proof
```

### 2. Настройка окружения

```bash
# Скопировать файл конфигурации
cp .env.example .env

# Запустить все контейнеры
make up

# Дождаться запуска (первый раз ~2-3 минуты)
make status
```

### 3. Инициализация базы данных

```bash
# Применить миграции и заполнить начальными данными
make fresh
```

### 4. Доступ к приложению

| Сервис | URL | Описание |
|--------|-----|----------|
| Приложение | http://127.0.0.1:7777 | Основное приложение |
| Админ-панель | http://127.0.0.1:7777/admin | Filament (требуется is_admin=true) |
| MinIO Console | http://127.0.0.1:9001 | Управление файлами |
| Mailpit | http://127.0.0.1:8026 | Просмотр отправленных писем |

## Доступные команды

### Docker

```bash
make up             # Запустить все контейнеры
make down           # Остановить все контейнеры
make rebuild        # Пересобрать и перезапустить
make status         # Показать статус контейнеров
make logs           # Показать логи контейнеров
```

### База данных

```bash
make migrate        # Применить миграции
make migrate-fresh  # Сбросить БД и применить миграции
make seed           # Заполнить начальными данными
make fresh          # migrate-fresh + seed
```

### Разработка

```bash
make test           # Запустить тесты
make pint           # Форматирование кода (Laravel Pint)
make phpstan        # Статический анализ (PHPStan)
make lint           # Все проверки
```

### Shell

```bash
make shell          # Войти в контейнер как www-data
make shell-root     # Войти в контейнер как root
make tinker         # Запустить Laravel Tinker (REPL)
```

### Artisan

```bash
make artisan CMD="route:list"     # Любая artisan-команда
make artisan CMD="queue:work"     # Обработка очередей
```

### Frontend

```bash
make npm CMD="install"    # Установить зависимости
make npm CMD="run dev"    # Запустить dev-сервер
make npm-build            # Собрать production-ассеты
```

## Структура проекта

```
app/
  Modules/                    # Модульный монолит (12 bounded contexts)
    Identity/                 # Аутентификация пользователей
    Property/                 # Управление объектами недвижимости
    Protocol/                 # Протоколы (check-in/check-out)
    Participation/            # Участники и приглашения
    Acceptance/               # Акцепт и подписи
    Billing/                  # Оплата и entitlements
    Notification/             # Email-уведомления
    Document/                 # Генерация PDF
    Evidence/                 # Фото с SHA-256 хешами
    Lifecycle/                # Политики хранения данных
    Catalog/                  # Каталог комнат/элементов
    Localization/             # Мультиязычность

  Filament/
    Resources/                # Ресурсы админ-панели

docker/
  php/                        # Dockerfile и конфигурация PHP
  nginx/                      # Nginx (local/production)
  entrypoint.sh               # Инициализация контейнера
  supervisord.conf            # Управление процессами

resources/
  views/
    documents/                # Blade-шаблоны PDF (5 типов)
    qr/                       # Страницы верификации QR

tests/
  Feature/                    # Функциональные тесты
  Unit/                       # Юнит-тесты
```

## Модули приложения

### Identity
Аутентификация, регистрация, управление профилем. Без PESEL и глобального типа пользователя.

### Property
Объекты недвижимости с типом декларации (`owner_declared` / `tenant_declared`).

### Protocol
Протоколы приёма-передачи:
- `CHECK_IN` — заселение (bilateral: требует 2 подписи)
- `CHECK_OUT` — выселение (unilateral: 1 подпись + окно возражений)

### Participation
Приглашения через magic-link, хранение `token_hash` (не raw токен), истечение 72ч.

### Acceptance
Электронные подписи с `consent_text_snapshot`, IP, user-agent, device fingerprint.

### Billing
HARD-GATE: оплата через Przelewy24 создаёт entitlement, который потребляется при генерации PDF.

### Document
5 типов PDF-шаблонов:
- `bilateral_checkin` — двусторонний check-in
- `unilateral_tenant_checkin` — односторонний (арендатор)
- `checkout_landlord` — check-out от арендодателя
- `checkout_tenant` — check-out от арендатора
- `checkout_no_baseline` — check-out без baseline

### Evidence
Фотографии с SHA-256 хешами, хранение в MinIO.

### Lifecycle
- `access_expires_at` = paid_at + 12 месяцев
- `retention_until` = access_expires_at + 3 года
- Автоматический переход в архив и soft-delete

### Catalog
Каталог комнат и элементов с translatable JSON, заморозка snapshot при создании протокола.

### Localization
Таблица `locales`, ровно один `is_default = pl`.

## Переменные окружения

| Переменная | Описание | По умолчанию |
|------------|----------|--------------|
| `APP_ENV` | Окружение | `local` |
| `APP_DEBUG` | Режим отладки | `true` |
| `DB_HOST` | Хост PostgreSQL | `db` |
| `DB_DATABASE` | Имя базы данных | `rent2proof` |
| `REDIS_HOST` | Хост Redis | `redis` |
| `AWS_ENDPOINT` | MinIO endpoint | `http://minio:9000` |
| `AWS_BUCKET` | Имя bucket | `rent2proof` |
| `MAIL_HOST` | SMTP сервер | `mailpit` |
| `PRZELEWY24_MERCHANT_ID` | ID мерчанта P24 | — |
| `PRZELEWY24_POS_ID` | POS ID | — |
| `PRZELEWY24_CRC` | CRC ключ | — |
| `PRZELEWY24_SANDBOX` | Sandbox режим | `true` |

## Тестирование

```bash
# Запуск всех тестов
make test

# Запуск конкретного теста
make artisan CMD="test --filter=DocumentHashFreezeTest"

# Тесты с покрытием
make artisan CMD="test --coverage"
```

### Стражи (Guards)

Система содержит 12 критических стражей, покрытых тестами:

| # | Страж | Описание |
|---|-------|----------|
| 1 | Checkout acceptance | Check-out не требует акцепта второй стороны |
| 2 | Tenant checkin | Check-in от арендатора без landlord — не bilateral |
| 3 | Unilateral notification | Уведомление в одностороннем документе |
| 4 | PDF signers | Инициатор и подписавшие в PDF |
| 5 | Catalog snapshot | Правка каталога не меняет запечатанный протокол |
| 6 | Payment flexibility | Платёж не hardcoded per protocol |
| 7 | Reference modes | Три reference_mode работают |
| 8 | Snapshot immutable | Snapshot заморожен после создания |
| 9 | QR privacy | QR не раскрывает PII |
| 10 | Photo hash | Фото в MinIO с SHA-256 |
| 11 | Checkout asymmetry | Check-out не симметричен |
| 12 | HARD-GATE | Entitlement требуется для PDF |

## Развёртывание в production

### 1. Подготовка сервера

```bash
# Установить Docker и Docker Compose
curl -fsSL https://get.docker.com | sh
apt install docker-compose-plugin
```

### 2. Конфигурация

```bash
# Создать .env для production
cp .env.example .env

# Обязательно изменить:
# APP_ENV=production
# APP_DEBUG=false
# APP_KEY=base64:... (сгенерировать через make artisan CMD="key:generate")
# DB_PASSWORD=secure_password
# PRZELEWY24_SANDBOX=false
# PRZELEWY24_MERCHANT_ID, PRZELEWY24_POS_ID, PRZELEWY24_CRC
```

### 3. Запуск

```bash
make up
make fresh
make npm-build
```

### 4. Настройка Nginx (внешний)

```nginx
server {
    listen 443 ssl http2;
    server_name rent2proof.pl;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    location / {
        proxy_pass http://127.0.0.1:7777;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

## API Endpoints

### Верификация QR

```
GET /verify/{hash}              # HTML страница верификации
POST /verify/api                # JSON API верификации
  Body: { "hash": "sha256..." }
  Response: { "valid": true/false, "type": "check_in", "legal_mode": "bilateral" }
```

### Webhook Przelewy24

```
POST /webhook/przelewy24        # Обработка платежей
```

## Таблица соответствия полей (D10)

### Основная таблица: protocols

| Поле ТЗ §13.1 | Поле в коде | Тип | Описание |
|---------------|-------------|-----|----------|
| inspection_id | id | UUID | Уникальный идентификатор протокола |
| type | type | enum | CHECK_IN / CHECK_OUT |
| initiator_role | initiator_role | enum | LANDLORD / TENANT |
| counterparty_role | counterparty_role | enum | LANDLORD / TENANT |
| legal_mode | legal_mode | enum | BILATERAL_COMPLETED / UNILATERAL_TENANT / UNILATERAL_LANDLORD |
| status | status | enum | draft → pending_counterparty → pending_signatures → signed → completed |
| property | property_id | UUID FK | Ссылка на объект недвижимости |
| title | title | string | Название протокола |
| description | description | text | Описание |
| scheduled_at | scheduled_at | datetime | Запланированная дата осмотра |
| completed_at | completed_at | datetime | Дата завершения |
| document_hash | document_hash | string(64) | SHA-256 хеш PDF (D5: замораживается) |
| created_at | created_at | datetime | Дата создания |

### Асимметричные поля (check-out)

| Поле ТЗ | Поле в коде | Тип | Описание |
|---------|-------------|-----|----------|
| act_issued_at | act_issued_at | datetime | Дата издания акта (только check-out) |
| objection_window_ends_at | objection_window_ends_at | datetime | Конец окна возражений (72ч по умолчанию) |
| reference_mode | reference_mode | enum | SYSTEM_BASELINE / UPLOADED_REFERENCE / NO_REFERENCE |
| linked_checkin_id | linked_checkin_id | UUID FK | Ссылка на связанный check-in |
| deposit_amount | deposit_amount | decimal | Сумма депозита |

### Lifecycle поля (D8)

| Поле ТЗ | Поле в коде | Тип | Описание |
|---------|-------------|-----|----------|
| paid_at | paid_at | datetime | Дата оплаты |
| access_expires_at | access_expires_at | datetime | paid_at + 12 месяцев |
| retention_until | retention_until | datetime | access_expires_at + 3 года |

### Связанные таблицы

| Таблица ТЗ | Таблица в коде | Описание |
|------------|----------------|----------|
| counterparty_participations | participants | Участники протокола |
| rooms | protocol_rooms | Комнаты с name_snapshot (G5) |
| elements | protocol_items | Элементы осмотра |
| meter_readings | protocol_meters | Показания счётчиков |
| keys | protocol_keys | Ключи |
| defects | protocol_defects | Дефекты |
| evidences | evidences | Фотографии с SHA-256 хешами |
| acceptances | acceptances | Подписи с forensic-данными |
| objections | protocol_objections | Возражения (check-out) |
| comments | protocol_comments | Комментарии участников |
| counterparty_photos | counterparty_photos | Фото второй стороны |
| inspection_events | inspection_events | Timeline событий (D7) |
| documents | generated_documents | Сгенерированные PDF |
| reference_documents | reference_documents | Загруженные справочные документы |
| invitation_tokens | invitation_tokens | Magic-link токены (G3) |
| payments | payments | Платежи Przelewy24 |
| entitlements | entitlements | Права на действия |
| entitlement_usages | entitlement_usages | Использование прав |

### Расхождения в именовании

| ТЗ | Код | Причина |
|----|-----|---------|
| inspections | protocols | Более точное название для документа |
| counterparty_participations | participants | Короче, включает и инициатора |
| elements | protocol_items | Более специфичное название |

**Примечание**: Каноническая таблица называется `protocols`, не `inspections`. Таблица `participants` соответствует `counterparty_participations` из ТЗ.

## Лицензия

Проприетарная лицензия — все права защищены.

## Авторы

- Разработка: [Ваше имя/компания]
- Контакт: email@example.com
