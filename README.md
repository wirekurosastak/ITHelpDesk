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
| `users` | API felhasználók | `id`, `role_id`, `name`, `email`, `password`, `is_approved`, `is_suspended`, `last_seen_at` |
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

### Jogosultság-mátrix

A táblázatban szereplő jelölések:
- ✅ **Engedélyezett**
- ❌ **Tiltott** (HTTP 403)
- 🔒 **Részleges** – feltételes hozzáférés (ld. megjegyzés)

| Művelet / Végpont | Employee | IT Support | Admin |
| --- | :---: | :---: | :---: |
| **Regisztráció** `POST /auth/register` | ✅ | ✅ | ✅ |
| **Bejelentkezés** `POST /auth/login` | ✅ | ✅ | ✅ |
| **Profil lekérés** `GET /auth/me` | ✅ | ✅ | ✅ |
| **Token frissítés** `POST /auth/refresh` | ✅ | ✅ | ✅ |
| **Kijelentkezés** `POST /auth/logout` | ✅ | ✅ | ✅ |
| **Heartbeat (Státusz)** `POST /auth/heartbeat` | ✅ | ✅ | ✅ |
| **Kategóriák listája** `GET /categories` | ✅ | ✅ | ✅ |
| **Címkék listája** `GET /tags` | ✅ | ✅ | ✅ |
| **Rendszer státusz** `GET /status` | ❌ | ✅ | ✅ |
| **Felhasználókezelés** `GET, POST, PATCH, DELETE /users/*` | ❌ | ❌ | ✅ |
| **Hibajegyek listázása** `GET /tickets` | 🔒 csak saját | ✅ mind | ✅ mind |
| **Hibajegy létrehozása** `POST /tickets` | ✅ | ✅ | ✅ |
| **Hibajegy megtekintése** `GET /tickets/{id}` | 🔒 csak saját | ✅ | ✅ |
| **Hibajegy módosítása** `PATCH /tickets/{id}` | 🔒 ld. ¹ | ✅ ld. ² | ✅ ld. ² |
| **Hibajegy törlése** `DELETE /tickets/{id}` | ❌ | ❌ | ✅ |
| **Fájl feltöltése** `POST /tickets/{id}/attachments` | 🔒 csak saját jegyre | ✅ | ✅ |
| **Fájl letöltése** `GET /attachments/{id}/download` | 🔒 csak saját jegyről | ✅ | ✅ |

**¹ Employee módosítási korlátok:**
- Csak a `description` mező módosítható
- Csak `open` státuszú jegyen (a `in_progress` és `closed` jegyek zároltak)
- Csak saját hibajegyein

**² IT Support / Admin módosítható mezők:**
`title`, `description`, `status`, `priority`, `category_id`, `assigned_to`, `tags[]`
- Az `assigned_to` értéke csak IT Support vagy Admin szerepkörű felhasználó lehet

### Üzleti szabályok összefoglalója

| Szabály | Részlet |
| --- | --- |
| Jelszótárolás | Bcrypt hash – nyílt jelszó sosem kerül adatbázisba |
| JWT token érvényesség | 60 perc (konfiguráció: `JWT_TTL` env változó) |
| Új regisztrációk | Az újonnan regisztrált fiókok (`is_approved = false`) adminisztrátori jóváhagyásig nem léphetnek be. |
| Felfüggesztés | Felfüggesztett (`is_suspended = true`) fiókkal nem lehet bejelentkezni, aktív munkamenet esetén kijelentkeztetésre kerül. |
| Online jelenlét | A kliens percenként `/auth/heartbeat` kérést küld. A backend 5 percig tekinti online-nak a felhasználót. |
| Fájl méretkorlát | max. 10 MB / feltöltés |
| Engedélyezett fájltípusok | jpg, jpeg, png, gif, webp, pdf, doc, docx, xls, xlsx, ppt, pptx, txt, log, csv, zip, tar, gz, 7z |
| Assignee korlát | Hibajegyet csak IT Support vagy Admin szerepkörű felhasználóhoz lehet rendelni |
| Adatbázis kaszkád | Hibajegy törlésekor a csatolmány rekordok és a feltöltött fájlok is törlődnek |



## API végpontok

### Hitelesítés

| Metódus | Végpont | Jogosultság | Body |
| --- | --- | --- | --- |
| `POST` | `/api/auth/register` | Nyilvános | `name`, `email`, `password` (jóváhagyás szükséges) |
| `POST` | `/api/auth/login` | Nyilvános | `email`, `password` |
| `GET` | `/api/auth/me` | Bejelentkezett | nincs |
| `POST` | `/api/auth/refresh` | Bejelentkezett | nincs |
| `POST` | `/api/auth/heartbeat` | Bejelentkezett | nincs |
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
| `GET` | `/api/status` | IT Support / Admin | PHP, Laravel, adatbázis és memória státusz |

### Felhasználókezelés (Admin)

Kizárólag Admin szerepkörrel érhető el:

| Metódus | Végpont | Body | Leírás |
| --- | --- | --- | --- |
| `GET` | `/api/users` | nincs | Felhasználók listázása |
| `PATCH` | `/api/users/{user}` | `name`, `email`, `role_id`, `is_approved`, `is_suspended` | Felhasználó adatainak és jogosultságainak módosítása |
| `PATCH` | `/api/users/{user}/approve` | nincs | Új felhasználó azonnali jóváhagyása |
| `POST` | `/api/users/{user}/suspend` | nincs | Felhasználó fiókjának felfüggesztése |
| `POST` | `/api/users/suspend-all` | nincs | Minden nem admin felhasználó felfüggesztése |
| `POST` | `/api/users/{user}/force-logout` | nincs | Felhasználó azonnali kijelentkeztetése |
| `POST` | `/api/users/logout-all` | nincs | Minden nem admin felhasználó kijelentkeztetése |
| `DELETE`| `/api/users/{user}` | nincs | Felhasználó végleges törlése (csak ha nincs hozzárendelt jegye) |

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

| Szerepkör | Név | Email | Jelszó |
| --- | --- | --- | --- |
| Employee | `Teszt Elek` | `employee@company.com` | `password` |
| IT Support | `Szőllősi Martin` | `it@company.com` | `password` |
| Admin | `Balla Tamás` | `admin@company.com` | `password` |

A seeder kategóriákat, címkéket, három felhasználót és két minta hibajegyet hoz létre.

## Ellenőrzés

```bash
php artisan test
./vendor/bin/pint
php artisan route:list --path=api
```

A jelenlegi tesztcsomag lefedi a regisztrációt, bejelentkezést, JWT alapú profil lekérést, dolgozói hozzáférési korlátokat, IT Support módosításokat, Admin törlést és a fájlcsatolmányok jogosultságait.
