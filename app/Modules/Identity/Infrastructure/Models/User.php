<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use App\Modules\Profiles\Infrastructure\Models\Profile;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use App\Modules\Profiles\Infrastructure\Models\UserSkill;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $password
 * @property bool $is_admin
 * @property string|null $locale
 * @property string|null $timezone
 * @property CarbonImmutable|null $last_active_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Profile|null $profile
 */
#[Fillable(['name', 'email', 'password', 'locale', 'timezone'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[UseFactory(UserFactory::class)]
final class User extends Authenticatable implements FilamentUser, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    // Mirrors the column defaults so freshly created models (strict mode) have every attribute.
    protected $attributes = [
        'is_admin' => false,
        'locale' => null,
        'timezone' => null,
        'last_active_at' => null,
        'two_factor_confirmed_at' => null,
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin && $this->hasVerifiedEmail();
    }

    /**
     * Private broadcast channel for this user's notifications.
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return "users.{$this->id}";
    }

    public function hasConsented(ConsentPurpose $purpose): bool
    {
        return $this->consents()->where('purpose', $purpose)->whereNull('revoked_at')->exists();
    }

    /**
     * @return HasOne<Profile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * @return HasMany<CvDocument, $this>
     */
    public function cvDocuments(): HasMany
    {
        return $this->hasMany(CvDocument::class);
    }

    /**
     * @return HasMany<UserSkill, $this>
     */
    public function skills(): HasMany
    {
        return $this->hasMany(UserSkill::class);
    }

    /**
     * @return HasMany<ProfileImport, $this>
     */
    public function profileImports(): HasMany
    {
        return $this->hasMany(ProfileImport::class);
    }

    /**
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'last_active_at' => 'immutable_datetime',
            'two_factor_confirmed_at' => 'immutable_datetime',
        ];
    }
}
