<?php

return PhpCsFixer\Config::create()
    ->setRules([
        '@PhpCsFixer'                           => true,
        'braces'                                => ['position_after_functions_and_oop_constructs' => 'same'],
        'array_syntax'                          => ['syntax' => 'long']
    ])
;