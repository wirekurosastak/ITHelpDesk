<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Role;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Roles
        Role::updateOrCreate(['id' => Role::EMPLOYEE_ID], ['name' => Role::EMPLOYEE]);
        Role::updateOrCreate(['id' => Role::IT_SUPPORT_ID], ['name' => Role::IT_SUPPORT]);
        Role::updateOrCreate(['id' => Role::ADMIN_ID], ['name' => Role::ADMIN]);

        // 2. Categories
        $hardware = Category::updateOrCreate(['name' => 'Hardware Issue']);
        $software = Category::updateOrCreate(['name' => 'Software Installation']);
        $network = Category::updateOrCreate(['name' => 'Network/Internet']);
        $access = Category::updateOrCreate(['name' => 'Access/Accounts']);

        // 3. Tags
        $urgent = Tag::updateOrCreate(['name' => 'Urgent']);
        Tag::updateOrCreate(['name' => 'Management']);
        Tag::updateOrCreate(['name' => 'Waiting for User']);
        $newEquipment = Tag::updateOrCreate(['name' => 'New Equipment']);

        // 4. Default Users
        $admin = User::updateOrCreate(
            ['email' => 'admin@company.com'],
            [
                'name' => 'Balla Tamás',
                'password' => Hash::make('password'),
                'role_id' => Role::ADMIN_ID,
                'is_approved' => true,
            ]
        );

        $support = User::updateOrCreate(
            ['email' => 'it@company.com'],
            [
                'name' => 'Szőllősi Martin',
                'password' => Hash::make('password'),
                'role_id' => Role::IT_SUPPORT_ID,
                'is_approved' => true,
            ]
        );

        $employee = User::updateOrCreate(
            ['email' => 'employee@company.com'],
            [
                'name' => 'Teszt Elek',
                'password' => Hash::make('password'),
                'role_id' => Role::EMPLOYEE_ID,
                'is_approved' => true,
            ]
        );

        // Pending user
        $pending = User::updateOrCreate(
            ['email' => 'beadando5@company.com'],
            [
                'name' => 'Beadandó Ötös',
                'password' => Hash::make('password'),
                'role_id' => Role::EMPLOYEE_ID,
                'is_approved' => false,
            ]
        );

        // 5. Default Tickets (Incidents: Hardware, Network) & Requests (Software, Access)
        
        // --- Employee Tickets ---
        Ticket::updateOrCreate(
            ['title' => 'Keyboard key broken'],
            [
                'description' => 'The spacebar on my keyboard is not registering keypresses.',
                'status' => Ticket::STATUS_OPEN,
                'priority' => Ticket::PRIORITY_LOW,
                'user_id' => $employee->id,
                'category_id' => $hardware->id,
            ]
        );

        Ticket::updateOrCreate(
            ['title' => 'No internet connection in Room 102'],
            [
                'description' => 'My ethernet port is dead and Wi-Fi is not connecting.',
                'status' => Ticket::STATUS_IN_PROGRESS,
                'priority' => Ticket::PRIORITY_MEDIUM,
                'user_id' => $employee->id,
                'category_id' => $network->id,
            ]
        );

        // --- Employee Requests ---
        $photoshop = Ticket::updateOrCreate(
            ['title' => 'Requesting Adobe Photoshop license'],
            [
                'description' => 'Our new marketing contractor needs a Photoshop license to begin working on the campaign assets.',
                'status' => Ticket::STATUS_IN_PROGRESS,
                'priority' => Ticket::PRIORITY_MEDIUM,
                'user_id' => $employee->id,
                'assigned_to' => $support->id,
                'category_id' => $software->id,
            ]
        );
        $photoshop->tags()->syncWithoutDetaching([$newEquipment->id]);

        Ticket::updateOrCreate(
            ['title' => 'Access to Github Organization'],
            [
                'description' => 'Please add me to the corporate GitHub organization.',
                'status' => Ticket::STATUS_OPEN,
                'priority' => Ticket::PRIORITY_LOW,
                'user_id' => $employee->id,
                'category_id' => $access->id,
            ]
        );

        // --- IT Support Tickets ---
        Ticket::updateOrCreate(
            ['title' => 'Server room air conditioning failure'],
            [
                'description' => 'The AC unit in the server room is making a grinding noise and the room temperature is rising.',
                'status' => Ticket::STATUS_OPEN,
                'priority' => Ticket::PRIORITY_HIGH,
                'user_id' => $support->id,
                'category_id' => $hardware->id,
            ]
        );

        Ticket::updateOrCreate(
            ['title' => 'VPN connection dropping repeatedly'],
            [
                'description' => 'The corporate VPN disconnects every 10 minutes for users in the European region.',
                'status' => Ticket::STATUS_IN_PROGRESS,
                'priority' => Ticket::PRIORITY_HIGH,
                'user_id' => $support->id,
                'category_id' => $network->id,
            ]
        );

        // --- IT Support Requests ---
        Ticket::updateOrCreate(
            ['title' => 'New laptop procurement request'],
            [
                'description' => 'Standard issue MacBook Pro for the new developer onboarding next week.',
                'status' => Ticket::STATUS_OPEN,
                'priority' => Ticket::PRIORITY_MEDIUM,
                'user_id' => $support->id,
                'category_id' => $software->id,
            ]
        );

        Ticket::updateOrCreate(
            ['title' => 'Password reset for server admin account'],
            [
                'description' => 'Need a temporary password reset for the staging server admin.',
                'status' => Ticket::STATUS_CLOSED,
                'priority' => Ticket::PRIORITY_LOW,
                'user_id' => $support->id,
                'category_id' => $access->id,
            ]
        );

        // --- Admin Tickets ---
        $doorScanner = Ticket::updateOrCreate(
            ['title' => 'Office door scanner not working'],
            [
                'description' => 'The RFID card scanner at the main entrance is offline. Employees cannot tap in.',
                'status' => Ticket::STATUS_OPEN,
                'priority' => Ticket::PRIORITY_HIGH,
                'user_id' => $admin->id,
                'category_id' => $hardware->id,
            ]
        );
        $doorScanner->tags()->syncWithoutDetaching([$urgent->id]);

        Ticket::updateOrCreate(
            ['title' => 'DNS zone transfer failed'],
            [
                'description' => 'Primary DNS server is not replicating zone files to the secondary server.',
                'status' => Ticket::STATUS_OPEN,
                'priority' => Ticket::PRIORITY_MEDIUM,
                'user_id' => $admin->id,
                'category_id' => $network->id,
            ]
        );

        // --- Admin Requests ---
        Ticket::updateOrCreate(
            ['title' => 'MS Office 365 Enterprise license'],
            [
                'description' => 'Upgrading the executive team to Office 365 E5 plan with advanced security.',
                'status' => Ticket::STATUS_IN_PROGRESS,
                'priority' => Ticket::PRIORITY_MEDIUM,
                'user_id' => $admin->id,
                'category_id' => $software->id,
            ]
        );

        Ticket::updateOrCreate(
            ['title' => 'Database access permission upgrade'],
            [
                'description' => 'Requesting read-write privileges on the production analytics schema for Balla Tamás.',
                'status' => Ticket::STATUS_OPEN,
                'priority' => Ticket::PRIORITY_HIGH,
                'user_id' => $admin->id,
                'category_id' => $access->id,
            ]
        );
    }
}
