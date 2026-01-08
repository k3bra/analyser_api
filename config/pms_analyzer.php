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
- Identify the Get Reservations endpoint path(s) and any query/filter parameters.
- Capture the exact endpoint/operation name from the docs in get_reservations_endpoint_names.
- For filters, set get_reservations_filters for check_in_date, check_out_date, and status.
- For filter source_fields, use the exact parameter names from the docs.
- If the PMS name is mentioned, set pms_name to the exact product name; otherwise set it to an empty string.
- If credentials or auth tokens are mentioned, set credentials_available true and list credential_types
  (username, password, api_key, token, client_id, client_secret, authorization). Do not include secrets.
- If something is not found, set available false, source_fields empty, confidence 0.
- Fields should reflect the Get Reservations (or equivalent) response when possible.

Return this schema exactly:
{
  "has_get_reservations_endpoint": true,
  "pms_name": "",
  "get_reservations_endpoint_names": [],
  "get_reservations_endpoints": [],
  "supports_webhooks": false,
  "credentials_available": false,
  "credential_types": [],
  "get_reservations_filters": {
    "check_in_date": { "available": true, "source_fields": ["checkInDate"], "confidence": 0.78 },
    "check_out_date": { "available": false, "source_fields": [], "confidence": 0.0 },
    "status": { "available": false, "source_fields": [], "confidence": 0.0 }
  },
  "get_reservations_available_filters": ["check_in_date", "status"],
  "fields": {
    "checkout_date": { "available": true, "source_fields": ["CheckOutDate"], "confidence": 0.92 },
    "check_in_date": { "available": true, "source_fields": ["CheckInDate"], "confidence": 0.88 },
    "first_name": { "available": true, "source_fields": ["guest.firstName"], "confidence": 0.97 },
    "last_name": { "available": false, "source_fields": [], "confidence": 0.0 },
    "country": { "available": false, "source_fields": [], "confidence": 0.0 },
    "mobile_phone": { "available": false, "source_fields": [], "confidence": 0.0 },
    "email": { "available": false, "source_fields": [], "confidence": 0.0 },
    "reservation_status": { "available": false, "source_fields": [], "confidence": 0.0 },
    "special_package": { "available": false, "source_fields": [], "confidence": 0.0 }
  },
  "reservation_statuses": ["confirmed", "cancelled"],
  "notes": ["Reservation status inferred from example response payload"]
}
PROMPT,
    ],
];
