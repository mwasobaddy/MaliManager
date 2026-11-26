export type DepositRefundStatus =
    | 'pending'
    | 'awaiting_inspection'
    | 'in_review'
    | 'approved'
    | 'rejected'
    | 'paid';

export type DepositDeduction = {
    id: number;
    description: string;
    amount: string;
    amount_cents: number;
    photos: { id: number; url: string }[];
};

export type DepositRefund = {
    id: number;
    lease_id: number;
    amount_cents: number;
    amount: string;
    currency: string;
    reason: string;
    status: DepositRefundStatus;
    status_label: string;
    status_variant: 'neutral' | 'success' | 'danger' | 'info' | 'warning';
    requested_by: number | null;
    requester_name: string | null;
    decided_by: number | null;
    decider_name: string | null;
    decided_at: string | null;
    decision_note: string | null;
    reviewed_by: number | null;
    reviewer_name: string | null;
    reviewed_at: string | null;
    review_note: string | null;
    deductions: DepositDeduction[];
    tenant_name: string | null;
    unit_name: string | null;
    property_name: string | null;
    paid_by: number | null;
    payer_name: string | null;
    paid_at: string | null;
    paid_reference: string | null;
    created_at: string | null;
};

export type LeaseOption = {
    value: number;
    label: string;
    deposit: number;
};

export type DepositRefundFormData = {
    lease_id: string;
    amount: string;
    reason: string;
};

export type PaginatedRefunds = {
    data: DepositRefund[];
    current_page: number;
    last_page: number;
    total: number;
};
