<?php

namespace Escalated\Laravel\Mail\Adapters;

use Escalated\Laravel\Mail\InboundMessage;
use Illuminate\Http\Request;

interface InboundAdapter
{
    /**
     * Parse the incoming webhook/API request into a normalized InboundMessage.
     */
    public function parseRequest(Request $request): InboundMessage;

    /**
     * Verify the authenticity of the incoming request (signature, token, etc.).
     */
    public function verifyRequest(Request $request): bool;
}
