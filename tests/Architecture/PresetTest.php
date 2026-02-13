<?php

// These test are preset tests for PEST in a PHP Project

declare(strict_types=1);

namespace tests\Architecture;

/*
 * It avoids the usage of die, var_dump, and similar functions, and ensures you are not using deprecated PHP functions.
 */
arch()->preset()->php();
arch()->preset()->security()->ignoring('Database');

// Here we ensure we use all the Laravel conventions, Use the Custom one instead of this one
// arch()->preset()->laravel();

// It ensures you are using strict types in all your files, that all your classes are final, and more.
// arch()->preset()->strict();
