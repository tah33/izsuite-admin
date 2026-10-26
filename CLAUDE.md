# izSuite: project rules

These rules apply to every task in this repository (the Laravel API and admin
panel) and in the Nuxt frontend beside it (`../izsuite-antigravity`). Read them
before starting. Follow them in all new code and in any code you change. Before
calling a task done, check every file you touched against them.

## 1. Controller → Service → Repository

- **Controllers** take the request, call one service, and return the response.
  - Every action wraps its whole body in a try/catch:

    ```php
    try {
        // call the service, build the response
    } catch (\Throwable $e) {
        report($e);
        throw $e;
    }
    ```

  - No business logic and no queries in a controller.
- **Services** (`app/Services/...`) hold the business logic. That means rules
  and decisions, transactions, side effects and activity logging.
- **Repositories** (`app/Repositories/...`) hold the queries. Every Eloquent or
  `DB` query lives in a repository method that a service calls, not in a
  controller or a service.
- Input is validated in FormRequest classes (`app/Http/Requests/...`).

## 2. Every component and Blade view stays under 200 lines

- This covers every `.vue` file in the frontend (pages, components, layouts)
  and every Blade view in `resources/views`, counting template, script and
  style together.
- When one grows past 200 lines, split it:
  - into child components or Blade partials;
  - logic into a composable;
  - shared styles into the shared stylesheet (`assets/css/main.css` in the
    frontend).

## 3. Short JSON responses

- An API response carries only the fields the frontend uses. Never return a
  whole model, unused columns or internal fields.
- A list returns what the list shows. A full record comes from its own
  endpoint, for example `GET /items/{id}`.
- When the frontend needs another field, add it to that endpoint's Resource.
  Do not make a second request for the whole record.

## 4. Responses go through a Resource class

- Shape every piece of data in a JSON response with a Laravel API Resource
  (`app/Http/Resources/...`). This includes settings, summaries and single
  values. Never return a model, array or collection straight from a controller.
- Keep the envelopes the app already uses:
  - `{ data }` for reads;
  - `{ message, data }` for writes;
  - `{ data, pagination }` for a page of a list.
- A bare `{ message }`, such as an error or a delete confirmation, needs no
  Resource. Neither do validation errors.
