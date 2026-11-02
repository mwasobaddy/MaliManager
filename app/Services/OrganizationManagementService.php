<?php

namespace App\Services;

use App\Actions\Organizations\Concerns\OrganizationHelpers;
use App\Actions\Organizations\DeleteOrganization;
use App\Actions\Organizations\SetOrganizationStatus;
use App\Actions\Organizations\UpdateOrganization;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Central organization management. Wraps {@see TenantService} so admins
 * can create fully-provisioned organizations (tenant, domain, default
 * sub-roles, owner membership) and manage their lifecycle.
 */
class OrganizationManagementService extends Service
{
    use OrganizationHelpers;

    public function __construct(private TenantService $tenants) {}

    public function create(User $owner, string $name, Plan $plan, ?string $email = null, ?string $phone = null): Organization
    {
        return $this->tenants->createOrganization($owner, $name, $plan, $email, $phone);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Organization $organization, array $data, User $actor): Organization
    {
        return app(UpdateOrganization::class)->handle($organization, $data, $actor);
    }

    /**
     * Suspend or reactivate an organization.
     *
     * Suspending drops the active sessions of every member so a suspension
     * takes effect immediately rather than at the next natural expiry — the
     * `organization.active` middleware blocks new tenant requests, but without
     * this an already-logged-in member would keep working until their cookie
     * aged out.
     */
    public function setStatus(Organization $organization, OrganizationStatus $status, User $actor): Organization
    {
        return app(SetOrganizationStatus::class)->handle($organization, $status, $actor);
    }

    /**
     * Create organizations from an uploaded CSV.
     *
     * Each row is provisioned through {@see TenantService::createOrganization()},
     * so an imported organization is identical to a hand-created one: tenant
     * row, unique subdomain, default sub-roles and an owner membership.
     *
     * Rows are imported independently — a bad row is recorded and skipped rather
     * than aborting the file — because a 200-row CSV with one typo should not
     * force the user to hand-edit the whole thing. The caller gets a summary it
     * can render back to the user.
     *
     * Recognised columns (header names are matched case-insensitively and may
     * use spaces or underscores): Name (required), Owner Email (required),
     * Email, Phone, Plan. Plan accepts a name or slug and falls back to
     * $defaultPlanId when blank.
     *
     * @return array{imported: int, failed: int, errors: array<int, array{row: int, message: string}>}
     */
    public function import(UploadedFile $file, ?int $defaultPlanId = null, ?User $actor = null): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return ['imported' => 0, 'failed' => 1, 'errors' => [['row' => 0, 'message' => 'The uploaded file could not be read.']]];
        }

        $header = fgetcsv($handle);

        if ($header === false || $header === [null]) {
            fclose($handle);

            return ['imported' => 0, 'failed' => 1, 'errors' => [['row' => 0, 'message' => 'The CSV has no header row.']]];
        }

        // Normalise "Owner Email" / "owner_email" / "OWNEREMAIL" -> owneremail
        $columns = array_map(
            fn ($column): string => strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $column)),
            $header
        );

        $plans = Plan::pluck('id', 'name')->merge(Plan::pluck('id', 'slug'));
        $defaultPlan = Plan::find($defaultPlanId) ?? Plan::orderBy('id')->first();

        $imported = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($row === [null] || implode('', array_map('trim', $row)) === '') {
                continue; // blank line
            }

            $record = [];

            foreach ($columns as $index => $column) {
                $record[$column] = trim((string) ($row[$index] ?? ''));
            }

            $name = $record['name'] ?? '';
            $ownerEmail = $record['owneremail'] ?? '';

            if ($name === '') {
                $errors[] = ['row' => $rowNumber, 'message' => "Row {$rowNumber}: Name is required."];

                continue;
            }

            if (! filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['row' => $rowNumber, 'message' => "Row {$rowNumber} ({$name}): Owner Email must be a valid email address."];

                continue;
            }

            $planName = $record['plan'] ?? '';

            $plan = ($planName !== '' ? $plans->get($planName) : null)
                ? Plan::find($plans->get($planName))
                : $defaultPlan;

            if (! $plan) {
                $errors[] = ['row' => $rowNumber, 'message' => "Row {$rowNumber} ({$name}): unknown plan \"{$planName}\"."];

                continue;
            }

            try {
                $owner = $this->findOrCreateOwner($ownerEmail);

                $this->tenants->createOrganization(
                    $owner,
                    $name,
                    $plan,
                    ($record['email'] ?? '') ?: null,
                    ($record['phone'] ?? '') ?: null,
                );

                $imported++;
            } catch (\Throwable $e) {
                // One bad row must not abort the rest of the file.
                $errors[] = ['row' => $rowNumber, 'message' => "Row {$rowNumber} ({$name}): ".$e->getMessage()];
            }
        }

        fclose($handle);

        if ($actor && $imported > 0) {
            activity()
                ->causedBy($actor)
                ->event('organizations.imported')
                ->log("Imported {$imported} organization(s) from CSV");
        }

        return [
            'imported' => $imported,
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }

    public function deleteOrganization(Organization $organization, User $actor): void
    {
        app(DeleteOrganization::class)->handle($organization, $actor);
    }
}
