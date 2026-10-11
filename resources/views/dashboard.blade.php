<x-layouts::app :title="__('Dashboard')">
    <div class="space-y-6" data-testid="dashboard-summary">
        <flux:heading size="xl">DuckLake log sink experiment</flux:heading>
        <flux:text>Use Apps to issue an ingest token, Search to inspect the lake, or connect an MCP client at <code>/mcp</code>.</flux:text>
        <flux:callout icon="exclamation-triangle" color="amber" heading="Raw logs are shipped unchanged by default">
            Secrets, tokens, PII, and exception context will leave the source application unless it rebinds the payload filter.
        </flux:callout>
    </div>
</x-layouts::app>
