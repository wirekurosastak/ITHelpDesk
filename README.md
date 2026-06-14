# Webprogramozás III. beadandó dokumentáció

## Téma és cél

A projekt egy belső **IT Helpdesk API**. A dolgozók hibajegyeket nyithatnak informatikai problémákra, az IT Support felhasználók kezelhetik és kioszthatják ezeket, az Admin pedig teljes körű felügyeleti joggal rendelkezik.

Az alkalmazás Laravelben készült, JSON REST API-ként működik, relációs adatbázist használ, az adatkezelést Eloquent ORM végzi. A védett végpontok eléréséhez JWT tokent kell küldeni:

```http
Authorization: Bearer <token>
Accept: application/json
```

## Adatmodell

| Tábla | Szerepe | Fontos mezők |
| --- | --- | --- |
| `roles` | Felhasználói szerepkörök | `id`, `name` |
| `users` | API felhasználók | `id`, `role_id`, `name`, `email`, `password` |
| `categories` | Hibajegy kategóriák | `id`, `name` |
| `tickets` | Hibajegyek | `id`, `title`, `description`, `status`, `priority`, `user_id`, `assigned_to`, `category_id` |
| `tags` | Hibajegy címkék | `id`, `name` |
| `ticket_tag` | Több-a-több kapcsolótábla | `ticket_id`, `tag_id` |
| `attachments` | Feltöltött fájlok metaadatai | `id`, `ticket_id`, `uploaded_by`, `file_path`, `original_name`, `mime_type`, `size` |

Kapcsolatok:

- Egy szerepkörhöz több felhasználó tartozhat.
- Egy kategóriához több hibajegy tartozhat.
- Egy felhasználó több hibajegyet nyithat.
- Egy IT/Admin felhasználóhoz több kiosztott hibajegy tartozhat.
- Egy hibajegyhez több csatolmány tartozhat.
- A hibajegyek és címkék között több-a-több kapcsolat van.

## Szerepkörök és jogosultságok

| Szerepkör | Jogosultság |
| --- | --- |
| Employee | Regisztrálhat, bejelentkezhet, hibajegyet hozhat létre, csak a saját hibajegyeit láthatja, és nyitott hibajegyen csak a leírást módosíthatja. Saját hibajegyhez fájlt tölthet fel és onnan tölthet le. |
| IT Support | Látja az összes hibajegyet, módosíthat státuszt, prioritást, kategóriát, címkét és felelőst. Nem törölhet hibajegyet. |
| Admin | IT Support jogok mellett hibajegyet is törölhet. |

Fontos üzleti szabályok:

- A dolgozó nem férhet hozzá más dolgozó hibajegyéhez vagy csatolmányához.
- Dolgozó csak `open` státuszú saját hibajegy leírását módosíthatja.
- Hibajegyet csak IT Support vagy Admin felhasználóhoz lehet hozzárendelni.
- A törlés csak Admin szerepkörrel engedélyezett.

## API végpontok

### Hitelesítés

| Metódus | Végpont | Jogosultság | Body |
| --- | --- | --- | --- |
| `POST` | `/api/auth/register` | Nyilvános | `name`, `email`, `password` |
| `POST` | `/api/auth/login` | Nyilvános | `email`, `password` |
| `GET` | `/api/auth/me` | Bejelentkezett | nincs |
| `POST` | `/api/auth/refresh` | Bejelentkezett | nincs |
| `POST` | `/api/auth/logout` | Bejelentkezett | nincs |

Sikeres login válasz:

```json
{
  "message": "Login successful.",
  "access_token": "...",
  "token_type": "bearer",
  "expires_in": 3600,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "employee@company.com",
    "role": {
      "id": 1,
      "name": "Employee"
    }
  }
}
```

### Lookup és státusz

| Metódus | Végpont | Jogosultság | Leírás |
| --- | --- | --- | --- |
| `GET` | `/api/categories` | Bejelentkezett | Kategóriák listája |
| `GET` | `/api/tags` | Bejelentkezett | Címkék listája |
| `GET` | `/api/status` | Bejelentkezett | PHP, Laravel, adatbázis és memória státusz |

### Hibajegyek

| Metódus | Végpont | Jogosultság | Body |
| --- | --- | --- | --- |
| `GET` | `/api/tickets` | Bejelentkezett | nincs |
| `POST` | `/api/tickets` | Bejelentkezett | `title`, `description`, `category_id`, opcionális: `priority`, `tags[]` |
| `GET` | `/api/tickets/{ticket}` | Saját jegy vagy IT/Admin | nincs |
| `PUT/PATCH` | `/api/tickets/{ticket}` | Saját jegy vagy IT/Admin | Employee: `description`; IT/Admin: `title`, `description`, `status`, `assigned_to`, `priority`, `category_id`, `tags[]` |
| `DELETE` | `/api/tickets/{ticket}` | Csak Admin | nincs |

Ticket létrehozási példa:

```json
{
  "title": "VPN connection fails",
  "description": "The VPN client cannot connect from home office.",
  "priority": "high",
  "category_id": 3,
  "tags": [1, 3]
}
```

Engedélyezett státuszok: `open`, `in_progress`, `closed`.
Engedélyezett prioritások: `low`, `medium`, `high`.

### Fájl feltöltés és letöltés

| Metódus | Végpont | Jogosultság | Body |
| --- | --- | --- | --- |
| `POST` | `/api/tickets/{ticket}/attachments` | Jegy tulajdonosa vagy IT/Admin | `multipart/form-data`, mező: `file`, max. 10 MB |
| `GET` | `/api/attachments/{attachment}/download` | Jegy tulajdonosa vagy IT/Admin | nincs |

A feltöltött fájl a Laravel `local` diszken, a `storage/app/private/attachments` mappa alatt tárolódik. Az adatbázisban a fájl elérési útja, eredeti neve, MIME típusa, mérete és feltöltő felhasználója is mentésre kerül.

Sikeres feltöltési válasz:

```json
{
  "message": "Attachment uploaded successfully.",
  "data": {
    "id": 1,
    "ticket_id": 1,
    "uploaded_by": 1,
    "file_path": "attachments/example.txt",
    "original_name": "example.txt",
    "mime_type": "text/plain",
    "size": 1024
  }
}
```

## Hibakezelés

Az API egységes JSON hibaválaszokat ad:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Jellemző státuszkódok:

| Kód | Jelentés |
| --- | --- |
| `200` | Sikeres lekérdezés vagy módosítás |
| `201` | Sikeres létrehozás |
| `204` | Sikeres törlés, nincs válasz body |
| `401` | Hiányzó vagy érvénytelen token |
| `403` | Nincs jogosultság |
| `404` | Nem létező rekord vagy fájl |
| `422` | Validációs hiba |

## Telepítés és futtatás

A tényleges beadandó projekt mappája: `ITHelpDesk`.

```bash
cd ITHelpDesk
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate:fresh --seed
php artisan serve
```

Laragon alatt a `ITHelpDesk` mappa állítható be projektként. A böngészős kliens a `http://127.0.0.1:8000/index.html` címen nyitható meg; a gyökér URL is ide irányít át. SQLite az alapértelmezett adatbázis, ezért külön MySQL konfiguráció nem szükséges.

## Mintaadatok

| Szerepkör | Email | Jelszó |
| --- | --- | --- |
| Employee | `employee@company.com` | `password` |
| IT Support | `it@company.com` | `password` |
| Admin | `admin@company.com` | `password` |

A seeder kategóriákat, címkéket, három felhasználót és két minta hibajegyet hoz létre.

## Ellenőrzés

```bash
php artisan test
./vendor/bin/pint
php artisan route:list --path=api
```

A jelenlegi tesztcsomag lefedi a regisztrációt, bejelentkezést, JWT alapú profil lekérést, dolgozói hozzáférési korlátokat, IT Support módosításokat, Admin törlést és a fájlcsatolmányok jogosultságait.
