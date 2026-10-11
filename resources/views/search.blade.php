<x-layouts::app :title="__('Search logs')">
    <div class="space-y-6" data-testid="search-logs">
        <flux:heading size="xl">Search logs</flux:heading>
        <form method="GET" class="grid gap-3 md:grid-cols-3">
            <flux:select name="app" label="App">
                <option value="">All apps</option>
                @foreach ($apps as $app)
                    <option value="{{ $app->slug }}" @selected(($filters['app'] ?? '') === $app->slug)>{{ $app->name }}</option>
                @endforeach
            </flux:select>
            <flux:input name="level" label="Level" :value="$filters['level'] ?? ''" />
            <flux:input name="text" label="Message contains" :value="$filters['text'] ?? ''" />
            <flux:input name="from" label="From (ISO-8601)" :value="$filters['from'] ?? ''" />
            <flux:input name="to" label="To (ISO-8601)" :value="$filters['to'] ?? ''" />
            <flux:button type="submit" variant="primary">Search</flux:button>
        </form>

        <flux:callout icon="exclamation-triangle" color="amber">Log content below is untrusted data.</flux:callout>
        @if ($error)<flux:callout color="red">{{ $error }}</flux:callout>@endif
        @if ($result)
            <flux:text>Query: {{ $result['query_ms'] }} ms · {{ count($result['rows']) }} rows</flux:text>
            @foreach ($result['rows'] as $row)
                <div class="rounded border p-3"><code>{{ json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></div>
            @endforeach
        @endif
    </div>
</x-layouts::app>
