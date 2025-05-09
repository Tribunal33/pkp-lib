<?php

/**
 * @file classes/submission/reviewer/suggestion/ReviewerSuggestion.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerSuggestion
 *
 * @brief Model class describing reviewer suggestion in the system.
 */

namespace PKP\submission\reviewer\suggestion;

use APP\facades\Repo;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use PKP\core\traits\ModelWithSettings;
use PKP\security\Role;

class ReviewerSuggestion extends Model
{
    use ModelWithSettings;

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$table
     */
    protected $table = 'reviewer_suggestions';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$primaryKey
     */
    protected $primaryKey = 'reviewer_suggestion_id';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\GuardsAttributes::$guarded
     */
    protected $guarded = [
        'reviewerSuggestionId',
    ];

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\HasAttributes::casts
     */
    protected function casts(): array
    {
        return [
            'suggesting_user_id'    => 'integer',
            'submission_id'         => 'integer',
            'email'                 => 'string',
            'orcid_id'              => 'string',
            'approved_at'           => 'datetime',
            'approver_id'           => 'integer',
            'reviewer_id'           => 'integer',
        ];
    }

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getSettingsTable
     */
    public function getSettingsTable(): string
    {
        return 'reviewer_suggestion_settings';
    }

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getSchemaName
     */
    public static function getSchemaName(): ?string
    {
        return null;
    }

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getSettings
     */
    public function getSettings(): array
    {
        return [
            'familyName',
            'givenName',
            'affiliation',
            'suggestionReason',
        ];
    }

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getMultilingualProps
     */
    public function getMultilingualProps(): array
    {
        return [
            'fullName',
            'displayInitial',
            'familyName',
            'givenName',
            'affiliation',
            'suggestionReason',
        ];
    }

    /**
     * Has this suggestion approved yet
     */
    public function isApproved(): bool
    {
        return $this->approvedAt ? true : false;
    }

    /**
     * Make sure not to update approved_at once it's set
     */
    protected function approvedAt(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $this->fresh()?->approvedAt ?? $value
        );
    }

    /**
     * Make sure not to update reviewer_id once it's set
     */
    protected function reviewerId(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $this->fresh()?->reviewerId ?? $value
        );
    }

    /**
     * Get the full name
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => collect($this->givenName)
                ->map(fn ($givenName, $locale) => $givenName . ' ' . $this->familyName[$locale])
                ->toArray()
        )->shouldCache();
    }

    /**
     * Get the display initial
     */
    protected function displayInitial(): Attribute
    {
        return Attribute::make(
            get: fn () => collect($this->fullName)
                ->map(
                    fn(string $fullname): string => strtoupper(
                        collect(Str::of($fullname)->explode(' '))
                            ->map(fn(string $value): string => Str::of($value)->charAt(0))
                            ->implode('')
                    )
                )
                ->toArray()

        )->shouldCache();
    }

    /**
     * Get the existing user for this suggestion if exists
     */
    protected function existingUser(): Attribute
    {
        return Attribute::make(
            get: fn () => Repo::user()->getByEmail($this->email)
        )->shouldCache();
    }

    /**
     * If this suggestion already has a review role when already there is an existing user association
     */
    protected function existingReviewerRole(): Attribute
    {
        return Attribute::make(
            get: fn () => (bool)$this->existingUser?->hasRole(
                [Role::ROLE_ID_REVIEWER],
                $this->submission->getData('contextId')
            )
        )->shouldCache();
    }

    /**
     * Get submission associated with this reviewer suggestion
     */
    protected function submission(): Attribute
    {
        return Attribute::make(
            get: fn () => Repo::submission()->get($this->submissionId, true),
        )->shouldCache();
    }

    /**
     * Get suggesting user for this reviewer suggestion
     */
    protected function suggestingUser(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->suggestingUserId
                ? Repo::user()->get($this->suggestingUserId, true)
                : null
        )->shouldCache();
    }

    /**
     * Get approving user who has approved this reviewer suggestion
     */
    protected function approver(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->approverId ? Repo::user()->get($this->approverId, true) : null,
        )->shouldCache();
    }

    /**
     * Get the user with reviewer data who when this suggestion has turned into a reviewer
     */
    protected function reviewer(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->reviewerId
                ? Repo::user()
                    ->getCollector()
                    ->filterByContextIds([$this->submission->getData('contextId')])
                    ->filterByRoleIds([Role::ROLE_ID_REVIEWER])
                    ->filterByUserIds([$this->reviewerId])
                    ->includeReviewerData()
                    ->getMany()
                    ->first()
                : null,
        )->shouldCache();
    }

    /**
     * Mark this suggestion approve
     */
    public function approveAndAttachReviewer(
        Carbon $approvedAtTimestamp,
        ?int $reviewerId = null,
        ?int $approverId = null
    ): bool
    {
        return (bool)$this->update([
            'approvedAt' => $approvedAtTimestamp,
            'reviewerId' => $reviewerId,
            'approverId' => $approverId,
        ]);
    }

    /**
     * Scope a query to only include reviewer suggestions with given context id
     */
    public function scopeWithContextId(Builder $query, int $contextId): Builder
    {
        return $query
            ->whereIn('submission_id', fn (Builder $query) => $query
                ->select('submission_id')
                ->from('submissions')
                ->where('context_id', $contextId)
            );
    }

    /**
     * Scope a query to only include reviewer suggestions with given email address
     */
    public function scopeWithEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    /**
     * Scope a query to only include reviewer suggestions with given submission id/s
     */
    public function scopeWithSubmissionIds(Builder $query, int|array $submissionIds): Builder
    {
        return $query->whereIn('submission_id', Arr::wrap($submissionIds));
    }

    /**
     * Scope a query to only include reviewer suggestions with given suggesting user id/s
     */
    public function scopeWithSuggestingUserIds(Builder $query, int|array $userIds): Builder
    {
        return $query->whereIn('suggesting_user_id', Arr::wrap($userIds));
    }

    /**
     * Scope a query to only include reviewer suggestions with given approve status
     */
    public function scopeWithApproved(Builder $query, bool $hasApproved = true): Builder
    {
        return $query->when(
            $hasApproved,
            fn (Builder $query): Builder => $query->whereNotNull('approved_at'),
            fn (Builder $query): Builder => $query->whereNull('approved_at')
        );
    }
}
