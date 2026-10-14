<?php

/**
 * Record the API's current OpenAPI spec as the one this library is checked
 * against: copy it to spec/openapi.json and write its sha256 to spec.sha.
 * Run it only after the library has been updated for the spec.
 *
 * The spec comes from WATERMARK_API_SPEC (a path or URL), else the API
 * checkout next to this one.
 */

$root = dirname(__DIR__);
$source = getenv('WATERMARK_API_SPEC') ?: dirname($root) . '/watermark-api/resources/docs/build/openapi.json';
$bytes = @file_get_contents($source);

if ($bytes === false) {
    fwrite(STDERR, "Cannot read {$source}. Set WATERMARK_API_SPEC to the API's openapi.json (a path or a URL).\n");
    exit(1);
}

$spec = json_decode($bytes, true);

if (! is_array($spec) || ! isset($spec['openapi'], $spec['paths'])) {
    fwrite(STDERR, "{$source} is not an OpenAPI spec.\n");
    exit(1);
}

if (! is_dir("{$root}/spec")) {
    mkdir("{$root}/spec");
}

file_put_contents("{$root}/spec/openapi.json", $bytes);
file_put_contents("{$root}/spec.sha", hash('sha256', $bytes) . "\n");

echo "spec/openapi.json and spec.sha now record {$source} (sha256 " . hash('sha256', $bytes) . ").\n";
