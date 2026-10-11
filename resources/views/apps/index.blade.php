<x-layouts::app :title="__('Apps')">
    <div class="space-y-6" data-testid="apps-index">
        <flux:heading size="xl">Ingest applications</flux:heading>

        @if (session('issued_token'))
            <flux:callout color="green" heading="Copy this token now; it will not be shown again">
                <code>{{ session('issued_token.token') }}</code>
            </flux:callout>
        @endif

        <form method="POST" action="{{ route('apps.store') }}" class="flex gap-3">
            @csrf
            <flux:input name="name" label="Application name" required />
            <flux:button type="submit" variant="primary">Create app</flux:button>
        </form>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>App</flux:table.column>
                <flux:table.column>Accepted</flux:table.column>
                <flux:table.column>Flushed</flux:table.column>
                <flux:table.column>Last ingest</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($apps as $app)
                    <flux:table.row :key="$app->id">
                        <flux:table.cell>{{ $app->name }} <code>{{ $app->slug }}</code></flux:table.cell>
                        <flux:table.cell>{{ number_format($app->accepted_lines) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($app->flushed_lines) }}</flux:table.cell>
                        <flux:table.cell>{{ $app->last_ingested_at?->diffForHumans() ?? 'Never' }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
</x-layouts::app>
