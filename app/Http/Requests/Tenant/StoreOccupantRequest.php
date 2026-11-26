<?php

namespace App\Http\Requests\Tenant;

use App\Models\Payment;
use App\Models\Property;
use App\Support\LeaseAgreementTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOccupantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A tenant needs at least one reachable contact: without an email
     * *and* a phone there is nowhere to send codes, reminders, or the
     * portal link, and the account row cannot be created.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (blank($this->input('email')) && blank($this->input('phone'))) {
                $validator->errors()->add('email', 'Provide an email address or a phone number.');
            }
        });
    }

    public function rules(): array
    {
        $property = $this->route('property');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            // Email is optional: a caretaker capturing "John, 0712…, Hse 3"
            // records the tenancy now; the account link waits until an
            // email is known.
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female'])],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'moved_out'])],
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => ['integer', Rule::exists('units', 'id')->where(fn ($query) => $query->where('property_id', $this->propertyId($property)))],
            // Move-in now (the default) activates immediately and records the
            // deposit plus first-period rent in the same transaction; off
            // means a booking — inactive occupant, pending leases.
            'move_in_now' => ['nullable', 'boolean'],
            'move_in_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', Rule::in(Payment::METHODS)],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'lease' => ['nullable', 'array'],
            'lease.starts_at' => ['nullable', 'date'],
            // A move-in records the first rent immediately, so it needs an
            // amount up front; bookings seed 0.00 and price later.
            'lease.rent_amount' => ['nullable', Rule::requiredIf($this->boolean('move_in_now', true)), 'numeric', 'min:0'],
            'lease.rent_frequency' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'lease.rent_due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'lease.rent_due_weekday' => ['nullable', 'integer', 'min:0', 'max:6'],
            'lease.rent_due_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'lease.deposit' => ['nullable', 'numeric', 'min:0'],
            'lease.currency' => ['nullable', 'string', 'size:3'],
            'lease.agreement_text' => ['nullable', 'string'],
            'lease.agreement_document' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'lease.remove_agreement_document' => ['nullable', 'boolean'],
            // Move-in extras live on every lease this request creates, so
            // the tenant sees parking, pets and one-time fees before
            // signing: they print in the agreement via additional_charges.
            'lease.extras' => ['nullable', 'array'],
            'lease.extras.*.name' => ['required', 'string', 'max:255'],
            'lease.extras.*.kind' => ['required', 'string', Rule::in(['fixed', 'one_time'])],
            'lease.extras.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * The rich-text editor produces HTML; keep only the safe rich-text
     * allowlist so the stored agreement can be rendered without risk.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('gender') === '') {
            $this->merge(['gender' => null]);
        }

        if ($this->has('lease.agreement_text')) {
            $this->merge([
                'lease' => array_merge($this->input('lease', []), [
                    'agreement_text' => LeaseAgreementTemplate::sanitize(
                        (string) $this->input('lease.agreement_text'),
                    ),
                ]),
            ]);
        }
    }

    private function propertyId(?Property $property): ?int
    {
        return $property?->id;
    }
}
