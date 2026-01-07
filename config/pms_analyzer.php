<?php

return [
    'storage_disk' => env('PMS_STORAGE_DISK', 'local'),
    'max_upload_mb' => env('PMS_MAX_UPLOAD_MB', 20),
    'chunk_max_chars' => env('PMS_CHUNK_MAX_CHARS', 6000),
    'chunk_overlap_chars' => env('PMS_CHUNK_OVERLAP_CHARS', 300),
    'default_prompt_version' => env('PMS_PROMPT_VERSION', 'v1'),
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout' => env('OPENAI_TIMEOUT', 60),
        'base_uri' => env('OPENAI_BASE_URI', 'https://api.openai.com/v1'),
    ],
    'prompts' => [
        'v1' => <<<'PROMPT'
You are analyzing a chunk of PMS API documentation. Use semantic matching and best-effort inference.

Rules:
- Return strict JSON only. No markdown, no prose.
- Prefer recall over precision. If a field seems likely, mark it available but use lower confidence.
- Handle SOAP, REST, or GraphQL docs and nested objects.
- Confidence must be a number between 0 and 1.
- Use lowercase for reservation_statuses.
- If something is not found, set available false, source_fields empty, confidence 0.

Return this schema exactly:
{
  "has_get_reservations_endpoint": true,
  "supports_webhooks": false,
  "fields": {
    "checkout_date": { "available": true, "source_fields": ["CheckOutDate"], "confidence": 0.92 },
    "first_name": { "available": true, "source_fields": ["guest.firstName"], "confidence": 0.97 },
    "last_name": { "available": false, "source_fields": [], "confidence": 0.0 },
    "country": { "available": false, "source_fields": [], "confidence": 0.0 },
    "mobile_phone": { "available": false, "source_fields": [], "confidence": 0.0 },
    "email": { "available": false, "source_fields": [], "confidence": 0.0 },
    "reservation_status": { "available": false, "source_fields": [], "confidence": 0.0 },
    "property_name": { "available": false, "source_fields": [], "confidence": 0.0 },
    "special_package": { "available": false, "source_fields": [], "confidence": 0.0 }
  },
  "reservation_statuses": ["confirmed", "cancelled"],
  "notes": ["Reservation status inferred from example response payload"]
}
PROMPT,
    ],
];
