<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

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
            // Document permissions
            'view documents',
            'create documents',
            'edit documents',
            'delete documents',
            'approve documents',
            'reject documents',
            'download documents',
            'rate documents',
            'comment documents',
            'mark official documents',

            // Post permissions
            'view posts',
            'create posts',
            'edit posts',
            'delete posts',
            'like posts',
            'comment posts',

            // Group permissions
            'view groups',
            'create groups',
            'edit groups',
            'delete groups',
            'join groups',
            'leave groups',
            'manage group members',

            // Chat permissions
            'view chats',
            'create chats',
            'send messages',
            'delete messages',
            'create group chats',
            'manage group chats',

            // User management permissions
            'view users',
            'create users',
            'edit users',
            'delete users',
            'ban users',
            'unban users',
            'assign roles',

            // Report permissions
            'view reports',
            'create reports',
            'resolve reports',

            // Statistics permissions
            'view statistics',

            // AI chat permissions
            'use ai chat',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        $moderatorRole = Role::create(['name' => 'moderator']);
        $moderatorRole->givePermissionTo([
            'view documents',
            'approve documents',
            'reject documents',
            'download documents',
            'view posts',
            'delete posts',
            'view reports',
            'resolve reports',
            'view users',
            'ban users',
            'unban users',
            'view statistics',
            'use ai chat',
        ]);

        $lecturerRole = Role::create(['name' => 'lecturer']);
        $lecturerRole->givePermissionTo([
            'view documents',
            'create documents',
            'edit documents',
            'delete documents',
            'download documents',
            'rate documents',
            'comment documents',
            'mark official documents',
            'view posts',
            'create posts',
            'edit posts',
            'delete posts',
            'like posts',
            'comment posts',
            'view groups',
            'create groups',
            'edit groups',
            'join groups',
            'leave groups',
            'view chats',
            'create chats',
            'send messages',
            'create group chats',
            'view reports',
            'create reports',
            'use ai chat',
        ]);

        $studentRole = Role::create(['name' => 'student']);
        $studentRole->givePermissionTo([
            'view documents',
            'create documents',
            'edit documents',
            'delete documents',
            'download documents',
            'rate documents',
            'comment documents',
            'view posts',
            'create posts',
            'edit posts',
            'delete posts',
            'like posts',
            'comment posts',
            'view groups',
            'create groups',
            'edit groups',
            'join groups',
            'leave groups',
            'view chats',
            'create chats',
            'send messages',
            'create group chats',
            'view reports',
            'create reports',
            'use ai chat',
        ]);
    }
}
