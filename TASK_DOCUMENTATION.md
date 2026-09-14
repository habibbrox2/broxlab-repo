# BroxLab Task Documentation

## Summary
This task documents the active investigation for the BroxLab Laravel project. The work centers on three reported issues:

1. Missing JavaScript and CSS assets causing 404 and MIME-type problems
2. A browser error: `throttle is not defined`
3. A weather API endpoint returning HTTP 500

The session history also showed a tooling loop caused by incorrect file-read calls using a single `path` argument instead of the required `paths` array. That was a workflow issue, not a project issue, and the actual project files were then reviewed correctly.

---

## Project Context
BroxLab is a Laravel application with a hybrid architecture:

- Laravel routes handle some migrated routes
- legacy code remains in place for other areas
- static assets are served from the public folder and from Vite-built output
- the Blade layout references asset URLs and loaded CSS/JS bundles

Key files reviewed:
- `routes/web.php`
- `resources/views/layouts/app.blade.php`
- `vite.config.js`
- `package.json`
- `config/weather.php`
- `app/Http/Controllers/WeatherApiController.php`
- `app/Support/WeatherService.php`

---

## 1) Missing JS/CSS asset issue
### Observed symptoms
- frontend asset requests return 404
- browser reports MIME type issues
- the page may partially render but script/style features fail

### Relevant project behavior
The Vite configuration defines a custom output folder:

```js
build: {
  outDir: 'public/assets/laravel/dist',
  emptyOutDir: true,
  manifest: false,
  rollupOptions: {
    input: 'resources/js/app.js',
    output: {
      entryFileNames: 'app.js',
    },
  },
}
```

This means the app expects a built frontend bundle in the public asset folder for Laravel-style serving. If the build was not run, or the built file is not present, routes that depend on those assets will fail.

### Likely root cause
The project likely has a mismatch between:
- where Vite emits the bundle
- where the Blade templates expect it to exist
- whether the build step ran in the production/deployment environment

### Important note
The app is designed to serve built frontend output via `public/assets/laravel/dist`, and this must be generated before runtime asset requests work correctly.

---

## 2) `throttle is not defined` error
### Observed symptoms
The browser throws:

```js
throttle is not defined
```

### Relevant project evidence
The project contains utility code that defines a `throttle` helper in the frontend JS utilities, and there is also a global window assignment:

- `public/assets/js/shared/utils.js`
- `public/assets/js/shared/dom-helpers.js`

These indicate the helper is intended to exist globally in some contexts, but it may not be loaded before code that references it executes.

### Likely root cause
This is usually caused by one of the following:
- the script that defines `throttle` is not included before the code that calls it
- the code is being executed in an ES module context where the helper is not available globally
- the asset ordering or bundling is broken

### Conclusion
The issue is likely a frontend load-order or bundle-integration problem, not a server-side Laravel issue.

---

## 3) Weather API 500 error
### Relevant files
- `routes/web.php`
- `app/Http/Controllers/WeatherApiController.php`
- `app/Support/WeatherService.php`
- `config/weather.php`

### Route and controller flow
The route exists:

```php
Route::get('/weather/details', [WeatherApiController::class, 'details'])->name('weather.details');
```

The controller validates the `location` and `units` query parameters and calls the weather service:

```php
$result = $this->weather->getCurrentWeather($location, $units, $forecastDays);
```

### Service behavior
The weather service supports two modes:

- `mock` provider for local/test use
- `openweathermap` provider for live API requests

The default config is:

```php
'provider' => env('WEATHER_PROVIDER', 'mock'),
```

This means the app defaults to mock mode unless explicitly configured otherwise.

### Why this can still fail
If the provider is set to `openweathermap` without a valid API key, the service will return an error and the controller responds with a JSON error. If the request path or backend call throws unexpectedly, it may bubble up as a 500.

### Likely root cause
The weather endpoint is not obviously broken in route registration, but it can fail when:
- the provider is configured incorrectly
- the API key is missing or invalid
- a remote external request fails
- the controller receives a malformed request

### Important note
The default project config is intentionally safe for local development because it uses mock data, so the weather issue is likely environment-specific and tied to real API configuration or request parameters.

---

## Tooling loop and debugging note
During the investigation, the session repeatedly attempted file reads with invalid syntax. The main issue was that a tool expecting a `paths` array was being called with a single `path` value instead.

Example of the bug pattern:

```text
read_files(path="...")
```

Correct format:

```json
{
  "paths": ["file1.php", "file2.php"]
}
```

This was the reason the session appeared stuck: the software kept trying to read files without returning actual contents. Once the proper call structure was used, the real code could be inspected and documented.

---

## Task Status
The task is now documented and the current state is understood:

- asset build and load order issues need verification against the frontend build output
- the `throttle` runtime issue is a frontend script ordering/global binding problem
- the weather API issue is tied to provider config and external API behavior

---

## Next Recommended Steps
1. Ensure the Vite build has run and the generated bundle exists under the expected public folder.
2. Verify script asset order so the `throttle` helper is defined before it is used.
3. Check environment variables for the weather provider and confirm whether live weather data is expected.
4. Re-test the affected pages after the build and config checks.

---

## Final note
This task is a combination of implementation debugging and workflow debugging: one part is real application troubleshooting, and the other is correcting an invalid tool-call pattern that caused the investigation to loop. The project state is now better understood and documented for continuation.
