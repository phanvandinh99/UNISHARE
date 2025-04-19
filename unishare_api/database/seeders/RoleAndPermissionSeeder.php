<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User permissions
            'view any user',
            'view user',
            'create user',
            'update user',
            'delete user',
            'ban user',
            'unban user',
            'promote user',
            'demote user',

            // Document permissions
            'view any document',
            'view document',
            'upload document',
            'update document',
            'delete document',
            'delete any document',
            'approve document',
            'reject document',
            'download document',
            'rate document',
            'comment on document',

            // Post permissions
            'view any post',
            'view post',
            'create post',
            'update post',
            'delete post',
            'delete any post',
            'like post',
            'comment on post',

            // Group permissions
            'view any group',
            'view group',
            'create group',
            'update group',
            'delete group',
            'join group',
            'leave group',
            'invite to group',
            'remove from group',

            // Chat permissions
            'view any chat',
            'view chat',
            'create chat',
            'update chat',
            'delete chat',
            'send message',
            'delete message',
            'delete any message',

            // Report permissions
            'create report',
            'view any report',
            'resolve report',

            // Category permissions
            'view any category',
            'create category',
            'update category',
            'delete category',

            // Statistics permissions
            'view statistics',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $roles = [
            'student' => [
                'view any user',
                'view user',
                'view any document',
                'view document',
                'upload document',
                'update document',
                'delete document',
                'download document',
                'rate document',
                'comment on document',
                'view any post',
                'view post',
                'create post',
                'update post',
                'delete post',
                'like post',
                'comment on post',
                'view any group',
                'view group',
                'create group',
                'update group',
                'delete group',
                'join group',
                'leave group',
                'invite to group',
                'view any chat',
                'view chat',
                'create chat',
                'update chat',
                'delete chat',
                'send message',
                'delete message',
                'create report',
                'view any category',
            ],
            'lecturer' => [
                'view any user',
                'view user',
                'view any document',
                'view document',
                'upload document',
                'update document',
                'delete document',
                'download document',
                'rate document',
                'comment on document',
                'view any post',
                'view post',
                'create post',
                'update post',
                'delete post',
                'like post',
                'comment on post',
                'view any group',
                'view group',
                'create group',
                'update group',
                'delete group',
                'join group',
                'leave group',
                'invite to group',
                'remove from group',
                'view any chat',
                'view chat',
                'create chat',
                'update chat',
                'delete chat',
                'send message',
                'delete message',
                'create report',
                'view any category',
            ],
            'moderator' => [
                'view any user',
                'view user',
                'ban user',
                'unban user',
                'view any document',
                'view document',
                'upload document',
                'update document',
                'delete document',
                'delete any document',
                'approve document',
                'reject document',
                'download document',
                'rate document',
                'comment on document',
                'view any post',
                'view post',
                'create post',
                'update post',
                'delete post',
                'delete any post',
                'like post',
                'comment on post',
                'view any group',
                'view group',
                'create group',
                'update group',
                'delete group',
                'join group',
                'leave group',
                'invite to group',
                'remove from group',
                'view any chat',
                'view chat',
                'create chat',
                'update chat',
                'delete chat',
                'send message',
                'delete message',
                'delete any message',
                'create report',
                'view any report',
                'resolve report',
                'view any category',
                'create category',
                'update category',
                'delete category',
            ],
            'admin' => $permissions, // Admin gets all permissions
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::create(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }

        // Create a super admin user
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@unishare.edu',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        // Create a moderator user
        $moderator = User::create([
            'name' => 'Moderator',
            'email' => 'moderator@unishare.edu',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $moderator->assignRole('moderator');

        // Create a lecturer user
        $lecturer = User::create([
            'name' => 'Lecturer',
            'email' => 'lecturer@unishare.edu',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $lecturer->assignRole('lecturer');

        // Create a student user
        $student = User::create([
            'name' => 'Student',
            'email' => 'student@unishare.edu',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $student->assignRole('student');
    }
}
