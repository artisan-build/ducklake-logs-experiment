<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $token_hash
 * @property \Carbon\CarbonImmutable|null $last_ingested_at
 * @property int $accepted_lines
 * @property int $flushed_lines
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LogBatch> $batches
 * @property-read int|null $batches_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogApp newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogApp newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogApp query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogApp whereAcceptedLines($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogApp whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogApp whereFlushedLines($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogApp whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogApp whereLastIngestedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogApp whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogApp whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogApp whereTokenHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogApp whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperLogApp {}
}

namespace App\Models{
/**
 * @property string $id
 * @property int $log_app_id
 * @property string $idempotency_key
 * @property array<array-key, mixed>|null $payload
 * @property int $row_count
 * @property string $status
 * @property \Carbon\CarbonImmutable|null $claimed_at
 * @property \Carbon\CarbonImmutable|null $flushed_at
 * @property string|null $last_error
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\LogApp $app
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogBatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogBatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogBatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogBatch whereClaimedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogBatch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogBatch whereFlushedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogBatch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogBatch whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogBatch whereLastError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogBatch whereLogAppId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogBatch wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogBatch whereRowCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogBatch whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogBatch whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperLogBatch {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property string|null $two_factor_confirmed_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Passkeys\Passkey> $passkeys
 * @property-read int|null $passkeys_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUser {}
}

