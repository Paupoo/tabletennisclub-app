<?php

namespace Tests\Unit;

use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function test_method_set_first_name_attribute(): void
    {
        $user = new User();
        $user->first_name="aURÉliEN";

        $this->assertEquals('Aurélien', $user->first_name);
    }

    public function test_method_set_last_name_attribute(): void
    {
        $user = new User();
        $user->first_name="pAULUS";

        $this->assertEquals('Paulus', $user->first_name);
    }

    public function test_method_set_age(): void
    {
        // Start
        $user = new User();
        $user->birthdate = '1988-08-17';
        $age = Carbon::parse($user->birthdate)->age;
        
        // Change
        $user->setAge();

        // Assert
        $this->assertEquals($age, $user->age);
    }


    public function test_method_set_age_without_birthdate(): void
    {
        // Start
        $user = new User();

        // Change
        $user->setAge();

        // Assert
        $this->assertEquals('Unknown', $user->age); 
    }
}
