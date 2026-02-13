<?php

declare(strict_types=1);

namespace tests\Architecture;

// using declare strict types everywhere

arch()
    ->expect('App')
    ->toUseStrictTypes();

arch()
    ->expect('App')
    ->toUseStrictEquality();

//arch()
//    ->expect('App')
//    ->classes()
//    ->toHaveMethodsDocumented();
