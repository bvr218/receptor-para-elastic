# Documentación del proyecto (receptor-para-elastic)

Documento generado a partir del código y archivos de configuración del repositorio. No incluye suposiciones sobre despliegue, URLs de producción ni comportamiento no reflejado en el código.

---

## 1. Resumen

- **Framework:** Laravel 11 (`laravel/framework` ^11.31 en `composer.json`).
- **PHP:** ^8.2.
- **Dependencias relevantes:** `elasticsearch/elasticsearch` ^8.17, `filament/filament` ^3.2, `laravel/sanctum` ^4.0, `laravel/tinker` ^2.9.
- **Nombre en Composer:** `laravel/laravel` (descripción genérica del esqueleto Laravel).
- **Front de build:** Vite 6, Tailwind 3, Laravel Vite Plugin (`package.json`).

La aplicación registra peticiones HTTP en Elasticsearch, puede enriquecer el cuerpo con datos calculados a partir de dispositivos en PostgreSQL (egrowth), expone rutas API y web mínimas, y ofrece un panel Filament en `/admin` para configurar credenciales de Elasticsearch.

---

## 2. Estructura de directorios (relevante al dominio)

| Ruta | Contenido |
|------|-----------|
| `app/Console/Commands/` | Comando Artisan `devices:export`. |
| `app/Filament/` | Recurso Filament de configuración y páginas. |
| `app/Helpers/Utils.php` | Funciones globales autoloaded vía `composer.json`. |
| `app/Http/Controllers/` | `ApiRequestController`, `DatabaseController`, `Controller`. |
| `app/Http/Middleware/` | `LogRequests`. |
| `app/Models/` | `User`, `Configuration`, `RemoteDevice`. |
| `app/Providers/` | `AppServiceProvider`, `Filament\AdminPanelProvider`. |
| `app/Services/` | `ElasticsearchService`, `DatabaseService`. |
| `bootstrap/app.php` | Bootstrap Laravel 11: rutas, middleware global, excepción CSRF en `/`. |
| `config/` | Configuración estándar Laravel; `database.php` define conexión `pgsql` con variables `EGROWTH_*`. |
| `database/migrations/` | Tablas Laravel + `configuration` + `personal_access_tokens`. |
| `database/seeders/` | `DatabaseSeeder` crea un usuario de prueba. |
| `routes/web.php`, `routes/api.php`, `routes/console.php` | Rutas HTTP y comando `inspire`. |
| `public/` | Front controller y assets publicados de Filament. |
| `resources/` | `welcome.blade.php`, CSS/JS de Vite. |
| `tests/` | Tests de ejemplo PHPUnit. |

---

## 3. Arranque y scripts

### Composer (`composer.json`)

- **Autoload:** `App\` → `app/`, más `app/Helpers/Utils.php` en `files`.
- **Scripts:**
  - `post-autoload-dump`: `package:discover`, `filament:upgrade`.
  - `post-update-cmd`: publicación de assets Laravel.
  - `post-root-package-install`: copia `.env.example` → `.env` si no existe.
  - `post-create-project-cmd`: `key:generate`, SQLite si no existe, `migrate --graceful`.
  - **`dev`:** `npx concurrently` ejecuta en paralelo: `php artisan serve`, `php artisan queue:listen --tries=1`, `php artisan pail --timeout=0`, `npm run dev`.

### NPM (`package.json`)

- Scripts: `npm run dev` (Vite), `npm run build` (Vite build).
- DevDependencies: Vite 6, Tailwind 3, axios, concurrently, laravel-vite-plugin, postcss, autoprefixer.

### Salud

- Ruta de health de Laravel: `/up` (definida en `bootstrap/app.php`).

---

## 4. Variables de entorno

### Archivo `.env.example` (plantilla en el repo)

Incluye, entre otras:

- `APP_*`, `DB_CONNECTION=sqlite` (comentarios para MySQL).
- `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`.
- Variables **`EGROWTH_NAME_DATABASE`**, **`EGROWTH_USER_DATABASE`**, **`EGROWTH_PASSWORD_DATABASE`**, **`EGROWTH_HOST_DATABASE`**, **`EGROWTH_PORT_DATABASE`** (valores de ejemplo en `.env.example`; el archivo `.env` local puede diferir en host).

### Conexión PostgreSQL “egrowth” (`config/database.php`)

La conexión **`pgsql`** usa exclusivamente:

- `env('EGROWTH_HOST_DATABASE', '127.0.0.1')`
- `env('EGROWTH_PORT_DATABASE', '5432')`
- `env('EGROWTH_NAME_DATABASE', 'laravel')`
- `env('EGROWTH_USER_DATABASE', 'root')`
- `env('EGROWTH_PASSWORD_DATABASE', '')`

La entrada `'url' => env('DB_URL')` para `pgsql` está **comentada** en el archivo.

### Base por defecto

- `config/database.php`: `'default' => env('DB_CONNECTION', 'sqlite')`.
- En `.env.example`: `DB_CONNECTION=sqlite` (sin `DB_DATABASE` explícito; Laravel usa por defecto `database_path('database.sqlite')`).

---

## 5. Base de datos y modelos

### Migraciones (SQLite por defecto, salvo que se cambie `DB_CONNECTION`)

| Migración | Tablas |
|-----------|--------|
| `0001_01_01_000000_create_users_table.php` | `users`, `password_reset_tokens`, `sessions` |
| `0001_01_01_000001_create_cache_table.php` | `cache`, `cache_locks` |
| `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs` |
| `2025_01_17_171321_create_configuration_table.php` | `configuration` (`id`, `name`, `value`, timestamps) |
| `2025_02_13_173034_create_personal_access_tokens_table.php` | `personal_access_tokens` |

### Modelo `App\Models\Configuration`

- Tabla: `configuration`.
- `$fillable`: `name`, `value`.

### Modelo `App\Models\RemoteDevice`

- Tabla: **`egrowth_all_devices`**.
- Conexión: **`pgsql`** (no usa la conexión por defecto de la app).

### Modelo `App\Models\User`

- Estándar Laravel: `fillable` name, email, password; casts de password y `email_verified_at`.

### Seeder `Database\Seeders\DatabaseSeeder`

- Crea un usuario con `User::factory()` y atributos fijos: `name` => `Test User`, `email` => `test@example.com`.

---

## 6. Elasticsearch

### Configuración en base de datos (no en `.env`)

`ElasticsearchService` y la página Filament de creación leen/escriben filas en `configuration` con nombres:

- `elastic_host` — valor almacenado **sin** el prefijo `https://` en la BD; el cliente se construye con `"https://" . $host->value`.
- `elastic_user`
- `elastic_password`

Si falta cualquiera de los tres, `ElasticsearchService::__construct` lanza `\Exception` con mensaje **`Configure elastic primero`**.

### Cliente PHP

- `Elastic\Elasticsearch\ClientBuilder::create()`
- `setHosts(["https://" . $host->value])`
- `setSSLVerification(false)`
- `setBasicAuthentication($user->value, $password->value)`

### Índice y documento

- Método `indexRequest($data)`: índice fijo **`api_requests`**, cuerpo con:
  - `timestamp` => `now()` (Carbon/Laravel)
  - `request` => el array `$data` pasado por quien llama.

### Filament — validación y guardado (`CreateConfiguration.php`)

- Campos de formulario: `elastic_host` (label “Url de servidor elastic”, prefijo visual `https://`), `elastic_user`, `elastic_password`.
- Acción **Validar:** construye el mismo tipo de cliente, ejecuta `$client->ping()`; notificación de éxito o advertencia; propiedad `$this->validated`.
- Acción **Guardar:** deshabilitada hasta `$this->validated === true`; upsert de las tres filas en `configuration`.
- **Nota de código:** en el bloque “Guardar”, tras `create()` no se llama explícitamente a `save()` en las ramas `else` donde solo se asigna `$config->value`; el comportamiento de persistencia depende de cómo Eloquent trate esas instancias (documentado aquí como está en el código).

### Comando y exportación

- **`php artisan devices:export`** (`App\Console\Commands\ExportDevicesData`): llama a `ElasticsearchService::checkDevicesDataAndExport()`.

`checkDevicesDataAndExport()` (resumen fiel al código):

- Obtiene todos los `RemoteDevice`.
- Por cada dispositivo usa `token` y `key` (`device_key` en el CSV).
- Ejecuta búsqueda en índice `api_requests` con cuerpo que incluye `size` 1000, `_source`, `query.term` sobre **`request.token.keyword`**, orden `timestamp` desc.
- Construye filas `device_key`, `token`, `has_data` (`yes`/`no`), `error`.
- Escribe CSV en **`storage_path('app/devices_data_report.csv')`** y devuelve esa ruta.
- Hay un bloque `catch` externo que referencia `$deviceKey` y `$token` fuera del `foreach`; si esa excepción se disparara antes de definir esas variables en un bucle, en PHP podría haber variables indefinidas (comportamiento observado en el código tal cual).

---

## 7. Middleware global `LogRequests`

**Registro:** `bootstrap/app.php` añade `LogRequests` con `$middleware->append(LogRequests::class)`.

**Flujo (`handle`):**

1. `$token = $request->segment(3);` — tercer segmento de la ruta (p. ej. en `/a/b/TOKEN` sería `TOKEN`).
2. Si `$token` es truthy, `$parsing_data = $this->databaseService->dataParsing($token, $request->all());` si no, `$parsing_data = Null` (el código usa `Null`; en PHP el valor nulo `null` es insensible a mayúsculas).
3. Arma `$data` con: `headers`, `token`, `method`, `query`, `body`, `body_calculated`, `body_raw` (`file_get_contents('php://input')`), `ip`, `user_agent`.
4. Llama `$this->elasticsearchService->indexRequest($data)`.
5. Responde **`response()->json(['message' => 'Request capturada'], 200)`** y **no** llama a `$next($request)` — cualquier petición que pase por este middleware termina aquí sin ejecutar el controlador de la ruta.

Efecto: las rutas definidas después en la pila pueden no llegar a ejecutarse para peticiones que pasen por el middleware global (según el orden real del pipeline de Laravel 11 para middleware “append”).

---

## 8. Rutas

### `routes/web.php`

- `Route::any('/', [ApiRequestController::class, 'captureRequest'])->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);`

**Nota:** En el árbol del proyecto no existe una clase propia `App\Http\Middleware\VerifyCsrfToken`; Laravel usa `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken`. Si el alias no está definido, esta línea puede provocar error de clase no encontrada al resolver la ruta (estado del código según el repo).

### `routes/api.php`

- `GET /api/device` → `DatabaseController@getDevice` (prefijo `api` por convención Laravel).
- `GET /api/user` → closure que devuelve `$request->user()` con middleware `auth:sanctum`.
- `POST`, `PUT`, `GET`, `DELETE` **`/api/capture`** → `ApiRequestController@captureRequest`.

### `routes/console.php`

- Comando Artisan `inspire` (cita inspiradora) — estándar Laravel.

---

## 9. Controladores

### `ApiRequestController::captureRequest`

- Inyecta `ElasticsearchService`.
- `$token = $request->segment(3);`
- Construye array con `token`, `headers`, `method`, `query`, `body`, `ip`, `user_agent`.
- `indexRequest($data)` y JSON `['message' => 'Request capturada']`, 200.

### `DatabaseController::getDevice`

- Inyecta `DatabaseService`.
- `return $this->databaseService->getDeviceByToken();` **sin argumentos**.

### `DatabaseService::getDeviceByToken(string $token)`

- Firma del método: **requiere** `string $token`.
- Implementación: `RemoteDevice::where('token', $token)->first();`

Hay una discrepancia entre la llamada del controlador (sin parámetro) y la firma del servicio (parámetro obligatorio).

---

## 10. Servicio `DatabaseService` (parsing de sensores)

### Helpers (`app/Helpers/Utils.php`)

- **`searchInListOfDicts(array $listOfDicts, string $searchStr): array`** — recorre diccionarios y acumula entradas donde la clave o el valor (como string o JSON) coincide exactamente con `$searchStr`.
- **`parse_device_attributes(mixed $attributes): array`** — si es string, `json_decode`; si JSON inválido lanza `InvalidArgumentException`; si no, devuelve el array o `[]`.

### Mapa de tipos de sensor

`protected static array $sensorHandlers` asocia códigos de tipo (p. ej. `TER21`, `5TE`, `VP4`, …) con nombres de métodos `handle*`. En el código aparece **`'GERBIL' => 'handleGERBIL'`** pero **no** existe método `handleGERBIL` en la clase — si se resolviera ese handler, fallaría la llamada dinámica.

### `dataParsing(string $token, array $data): array`

- Carga dispositivo por token; si no existe, devuelve `[]`.
- Parsea `attributes` del dispositivo.
- Etapas: `digital_classic` (getSensorsTypes + getSensorsKeys), `sh_sensor`, `analog`; luego pulsos con `getSensorsTypesPulse` y `getSensorsKeysConfigServerPulse`.
- **Dentro del bucle de etapas hay `dd($stage);`** — detiene la ejecución en depuración en la primera etapa con tipos no vacíos.
- **En el `catch` global hay `dd(str($e));`** — `str()` no es función estándar de PHP en versiones habituales; el código tal cual puede error de función desconocida si se alcanza ese catch.

### Otros detalles documentados en código

- Varios handlers usan índices de canal del estilo `$key . $idx` o `$key . ($idx)` según el método.
- `mergeWithOriginalData` fusiona valores originales y calculados por sensor y timestamp.
- Import `use Elastic\Elasticsearch\ClientBuilder` en `DatabaseService.php` — **no** se usa en el cuerpo del archivo según el contenido leído.

---

## 11. Panel Filament

- **`App\Providers\Filament\AdminPanelProvider`:** panel `id('admin')`, `path('admin')`, `login()`, color primario Amber, descubre Resources/Pages/Widgets bajo `app/Filament/`, dashboard por defecto, widgets Account y FilamentInfo.
- **`ConfigurationResource`:** model `Configuration`, etiquetas en español, formulario/tabla vacíos en el resource; `getPages()` apunta el índice a `CreateConfiguration` (misma ruta que `create` en el array de páginas).
- Páginas: `CreateConfiguration` (formulario completo de Elastic), `EditConfiguration`, `ListConfigurations`.

---

## 12. Proveedores

- `bootstrap/providers.php`: `AppServiceProvider`, `AdminPanelProvider`.
- `AppServiceProvider`: `register` y `boot` vacíos.

---

## 13. CSRF (`bootstrap/app.php`)

- `validateCsrfTokens(except: ['/'])` — la ruta URI `/` queda excluida de verificación CSRF.

---

## 14. Tests

- **`tests/Feature/ExampleTest.php`:** `GET /` espera 200 (interacción con middleware global y Elasticsearch/BD según entorno).
- **`tests/Unit/ExampleTest.php`:** `assertTrue(true)`.
- **`phpunit.xml`:** `APP_ENV=testing`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array`; líneas de `DB_CONNECTION` / `DB_DATABASE` para SQLite en memoria están comentadas.

---

## 15. Archivos auxiliares en la raíz

- `README.md`: texto por defecto de Laravel (no describe este proyecto en concreto).
- `index.nginx-debian.html`: archivo HTML de ejemplo típico de paquetes Debian/nginx.

---

## 16. Mapa rápido “quién hace qué”

| Componente | Función |
|------------|---------|
| `LogRequests` | Indexa en Elastic payload ampliado con `body_calculated` si hay token en segmento 3; responde JSON y no delega al siguiente middleware. |
| `ApiRequestController` | Indexa payload más simple (mismo índice); segmento 3 como token. |
| `ElasticsearchService` | Cliente Elastic desde `configuration`; `indexRequest`; export CSV por dispositivos. |
| `DatabaseService` | Lectura `RemoteDevice` en PostgreSQL; parsing/cálculo por tipo de sensor. |
| Filament `CreateConfiguration` | Validar conexión Elastic y guardar `elastic_*` en SQLite (BD por defecto). |
| `devices:export` | CSV en `storage/app/devices_data_report.csv`. |

---

## 17. Seguridad (hechos del código, no recomendaciones generales)

- Credenciales de Elastic se guardan en texto en la tabla `configuration` de la BD por defecto de la app.
- `setSSLVerification(false)` en cliente Elasticsearch.
- `.env.example` contiene valores de ejemplo para PostgreSQL egrowth (riesgo si se copian a producción sin rotar).
- El middleware de logging responde siempre 200 con mensaje fijo tras intentar indexar.

---

*Fin del documento. Para cambios futuros, actualizar este archivo junto al código.*
