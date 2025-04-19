<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lecturer = User::role('lecturer')->first();
        $students = User::role('student')->take(15)->get();

        // Create course groups
        $courseGroups = [
            [
                'name' => 'Introduction to Computer Science',
                'description' => 'Group for CS101 students',
                'course_code' => 'CS101',
                'university' => 'UniShare University',
                'department' => 'Computer Science',
                'type' => 'course',
            ],
            [
                'name' => 'Data Structures and Algorithms',
                'description' => 'Group for CS201 students',
                'course_code' => 'CS201',
                'university' => 'UniShare University',
                'department' => 'Computer Science',
                'type' => 'course',
            ],
            [
                'name' => 'Database Systems',
                'description' => 'Group for CS301 students',
                'course_code' => 'CS301',
                'university' => 'UniShare University',
                'department' => 'Computer Science',
                'type' => 'course',
            ],
        ];

        foreach ($courseGroups as $groupData) {
            $group = Group::create(array_merge($groupData, [
                'creator_id' => $lecturer->id,
                'member_count' => 1,
            ]));

            // Add lecturer as admin
            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $lecturer->id,
                'role' => 'admin',
                'status' => 'approved',
                'joined_at' => now(),
            ]);

            // Add some students to the group
            $randomStudents = $students->random(rand(5, 10));
            foreach ($randomStudents as $student) {
                GroupMember::create([
                    'group_id' => $group->id,
                    'user_id' => $student->id,
                    'role' => 'member',
                    'status' => 'approved',
                    'joined_at' => now(),
                ]);

                // Update member count
                $group->increment('member_count');
            }
        }

        // Create interest groups
        $interestGroups = [
            [
                'name' => 'Programming Club',
                'description' => 'Group for programming enthusiasts',
                'type' => 'public',
            ],
            [
                'name' => 'AI Research Group',
                'description' => 'Discussions about artificial intelligence',
                'type' => 'public',
            ],
            [
                'name' => 'Study Buddies',
                'description' => 'Find study partners for exams',
                'type' => 'public',
            ],
        ];

        $student = $students->first();

        foreach ($interestGroups as $groupData) {
            $group = Group::create(array_merge($groupData, [
                'creator_id' => $student->id,
                'member_count' => 1,
                'university' => 'UniShare University',
            ]));

            // Add student as admin
            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $student->id,
                'role' => 'admin',
                'status' => 'approved',
                'joined_at' => now(),
            ]);

            // Add some other students to the group
            $randomStudents = $students->except($student->id)->random(rand(3, 8));
            foreach ($randomStudents as $randomStudent) {
                GroupMember::create([
                    'group_id' => $group->id,
                    'user_id' => $randomStudent->id,
                    'role' => 'member',
                    'status' => 'approved',
                    'joined_at' => now(),
                ]);

                // Update member count
                $group->increment('member_count');
            }
        }
    }
}
