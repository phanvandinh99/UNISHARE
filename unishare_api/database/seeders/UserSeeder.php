<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@dtu.vn',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'university' => 'Duy Tan University',
            'department' => 'Administration',
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        // Create moderator user
        $moderator = User::create([
            'name' => 'Moderator User',
            'email' => 'moderator@dtu.vn',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'university' => 'Duy Tan University',
            'department' => 'Content Moderation',
            'is_active' => true,
        ]);
        $moderator->assignRole('moderator');

        // Create lecturer user
        $lecturer = User::create([
            'name' => 'Lecturer User',
            'email' => 'lecturer@dtu.vn',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'university' => 'Duy Tan University',
            'department' => 'Computer Science',
            'is_active' => true,
        ]);
        $lecturer->assignRole('lecturer');

        // Create student users
        $student1 = User::create([
            'name' => 'Student One',
            'email' => 'student1@dtu.vn',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'university' => 'Duy Tan University',
            'department' => 'Computer Science',
            'student_id' => 'CS001',
            'is_active' => true,
        ]);
        $student1->assignRole('student');

        $student2 = User::create([
            'name' => 'Student Two',
            'email' => 'student2@dtu.vn',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'university' => 'Duy Tan University',
            'department' => 'Engineering',
            'student_id' => 'ENG001',
            'is_active' => true,
        ]);
        $student2->assignRole('student');

        // Create more random users
        User::factory(20)->create()->each(function ($user) {
            $user->assignRole('student');
        });
    }
}
