# Agent guide: write a log payload redaction filter

The driver ships raw messages, context, exception data, and extra fields unchanged by default.
Logs often contain passwords, API tokens, session cookies, authorization headers, personal data,
payment details, and values copied from user input. Installing the driver without a filter sends all
of that to the central sink.

Ask your coding agent to implement `ArtisanBuild\DucklakeLogDriver\Contracts\PayloadFilter` inside
the source application and bind it in that application's service provider:

```php
use App\Logging\RedactLogPayload;
use ArtisanBuild\DucklakeLogDriver\Contracts\PayloadFilter;

$this->app->bind(PayloadFilter::class, RedactLogPayload::class);
```

The method receives and returns an immutable `LogPayload`. Return a new payload with sensitive
keys/values replaced, or `null` to drop the event. Preserve `id`, `timestamp`, `level`, `channel`,
and enough structure for debugging. Redact recursively and case-insensitively. At minimum cover
passwords, secrets, tokens, cookies, authorization headers, credit-card fields, and application-
specific personal identifiers. Do not log from inside the filter; that can create recursion.

Require tests that prove the exact secret value never appears anywhere in the serialized outbound
request, including nested context and exception fields. Also test the drop path and malformed UTF-8.
The default pass-through implementation is intentional, so the application owns this policy and can
change it by rebinding the contract without forking the driver.
