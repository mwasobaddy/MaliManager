<?php

namespace App\Exceptions;

use Exception;

/**
 * Raised when the assistant cannot fulfil a request (no credential, model
 * error, or an out-of-bounds query). Caught by controllers to return a
 * friendly, non-leaking error to the client.
 */
final class AssistantUnavailableException extends Exception {}
