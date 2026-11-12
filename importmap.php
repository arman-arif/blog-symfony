<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 *
 * @return array<string, array{    // Import name as key, description of the imported file as value
 *     path: string,               // Logical, relative or absolute path to the file
 *     type?: 'js'|'css'|'json',   // Type of the file, defaults to 'js'
 *     entrypoint?: bool,          // Whether the file is an entrypoint, for 'js' only
 * }|array{
 *     version: string,            // Version of the remote package
 *     package_specifier?: string, // Remote "package-name/path" specifier, defaults to the import name
 *     type?: 'js'|'css'|'json',
 *     entrypoint?: bool,
 * }>
 */
return [
    'app' => ['path' => './assets/app.js', 'entrypoint' => true],
    'admin' => ['path' => './assets/admin.js', 'entrypoint' => true],
    'chart.js' => ['version' => '4.5.1'],
    '@kurkle/color' => ['version' => '0.3.4'],
    'quill' => ['version' => '2.0.3'],
    'quill/dist/quill.snow.css' => ['version' => '2.0.3', 'type' => 'css'],
    'lodash-es' => ['version' => '4.17.21'],
    'parchment' => ['version' => '3.0.0'],
    'quill-delta' => ['version' => '5.1.0'],
    'eventemitter3' => ['version' => '5.0.1'],
    'fast-diff' => ['version' => '1.3.0'],
    'lodash.clonedeep' => ['version' => '4.5.0'],
    'lodash.isequal' => ['version' => '4.5.0'],
];
