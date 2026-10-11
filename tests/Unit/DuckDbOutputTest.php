<?php

use App\Support\DuckDb;

it('returns every row between framing markers and ignores attach success output', function (): void {
    $output = <<<'JSON'
        {"Success":true}
        {"marker":"__DUCKLAKE_RESULT_START__"}
        {"event_id":"one","message":"first"}
        {"event_id":"two","message":"second"}
        {"marker":"__DUCKLAKE_RESULT_END__"}
        JSON;
    $method = new ReflectionMethod(DuckDb::class, 'decodeFramedOutput');

    expect($method->invoke(resolve(DuckDb::class), $output))->toBe([
        ['event_id' => 'one', 'message' => 'first'],
        ['event_id' => 'two', 'message' => 'second'],
    ]);
});
