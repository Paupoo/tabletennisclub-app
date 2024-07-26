<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ForceIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Indicates whether the default seeder should run before each test.
     *
     * @var bool
     */
    protected $seed = true;
    
    /**
     * A basic feature test example.
     */
    public function test_delete_force_indexes_set_all_value_to_null_in_database(): void
    {

        $response = $this->actingAs(User::find(1))
                        ->get('/admin/members/deleteForceIndex');

        foreach (User::all() as $user) {
            $this->assertNull($user->force_index);
        }

        $response->assertRedirect(route('members.index'));
    }

    public function test_set_force_index_are_correctly_calculated(): void
    {
        /**
         * 1 D4 => 1
         * 1 D6 => 2    (+1)
         * 1 E0 => 3    (+1)
         * 3 E2 => 6    (+3)
         * 1 E4 => 7    (+1)
         * 2 E6 => 11   (+4)
         * 2 NC => 11   (included with E6)
         */

        $checkReferences = [
            'D4' => 1,
            'D6' => 2,
            'E0' => 3,
            'E2' => 6,
            'E4' => 7,
            'E6' => 11,
            'NC' => 11,  
        ];

        foreach($checkReferences as $ranking => $force_index) {
            foreach(User::select('force_index')->where('is_competitor', true)->where('ranking', $ranking)->get() as $user) {
                $this->assertEquals($force_index, $user->force_index);
            }
        }
    }

    public function test_force_index_are_calculated_only_for_competitors(): void
    {
        
        foreach(User::where('is_competitor', true)->get() as $competitor) {
            $this->assertIsInt($competitor->force_index);
        }

        foreach(User::where('is_competitor', false)->get() as $competitor) {
            $this->assertNull($competitor->force_index);
        }

    }

}
