<?php

declare(strict_types=1);

namespace ArtisanBuild\DucklakeLogDriver;

use DateTimeImmutable;
use Monolog\LogRecord;

final readonly class LogPayload
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $id,
        public string $timestamp,
        public string $level,
        public string $channel,
        public string $message,
        public array $context,
        public array $extra,
    ) {}

    public static function fromRecord(LogRecord $record): self
    {
        return new self(
            id: self::uuid(),
            timestamp: DateTimeImmutable::createFromInterface($record->datetime)->format(DATE_ATOM),
            level: strtolower($record->level->getName()),
            channel: $record->channel,
            message: $record->message,
            context: self::normalize($record->context),
            extra: self::normalize($record->extra),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function normalize(array $values): array
    {
        $encoded = json_encode($values, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);

        return is_string($encoded) ? (array) json_decode($encoded, true) : [];
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
