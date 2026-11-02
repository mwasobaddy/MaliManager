export type DepositRefundStatus = 'pending' | 'approved' | 'rejected' | 'paid';

export type DepositRefund = {
    id: number;
    lease_id: number;
    amount_cents: number;
    amount: string;
    currency: string;
    reason: string;
    status: DepositRefundStatus;
    status_label: string;
    status_variant: 'neutral' | 'success' | 'danger' | 'info';
    requested_by: number | null;
    requester_name: string | null;
    decided_by: number | null;
    decider_name: string | null;
    decided_at: string | null;
    decision_note: string | null;
    tenant_name: string | null;
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
