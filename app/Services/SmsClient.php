<?php

namespace App\Services;

use AfricasTalking\SDK\AfricasTalking;

/**
 * Instance wrapper around the Africa's Talking SDK so SMS stays testable:
 * tests bind a fake subclass instead of hitting api.africastalking.com.
 * Sandbox mode is the SDK's own convention — username 'sandbox'.
 */
class SmsClient
{
    public function __construct(
        private string $username = '',
        private string $apiKey = '',
        private string $senderId = '',
    ) {
        $this->username = $username !== '' ? $username : (string) config('services.africastalking.username');
        $this->apiKey = $apiKey !== '' ? $apiKey : (string) config('services.africastalking.key');
        $this->senderId = $senderId !== '' ? $senderId : (string) config('services.africastalking.sender_id');
    }

    /**
     * @return array{status: string, data: mixed}
     */
    public function send(string $to, string $message): array
    {
        $gateway = new AfricasTalking($this->username, $this->apiKey);

        $options = ['to' => $to, 'message' => $message];

        if ($this->senderId !== '') {
            $options['from'] = $this->senderId;
        }

        return $gateway->sms()->send($options);
    }

    public function succeeded(array $result): bool
    {
        return ($result['status'] ?? null) === 'success';
    }
}
