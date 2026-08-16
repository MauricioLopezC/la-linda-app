<?php

use Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

return [
    /*
     * The paths where typescript-transformer will look for PHP classes
     * to transform, this will be the app path by default.
     */
    'auto_discover_types' => [
        app_path('Data'),
    ],

    /*
     * Transformers to use when transforming types.
     */
    'transformers' => [
        DataTypeScriptTransformer::class,
        EnumTransformer::class,
    ],

    /*
     * The path where the generated TypeScript file will be written.
     */
    'output_file' => resource_path('js/types/generated.d.ts'),

    /*
     * The formatter to use when generating TypeScript. Set to null so
     * Prettier runs via npm scripts on Windows/cross-platform without hanging.
     */
    'formatter' => null,

    /*
     * The writer to use when writing the transformed types.
     */
    'writer' => GlobalNamespaceWriter::class,
];
