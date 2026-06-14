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
        Role::updateOrCreate(['id' => Role::EMPLOYEE_ID], ['name' => Role::EMPLOYEE]);
        Role::updateOrCreate(['id' => Role::IT_SUPPORT_ID], ['name' => Role::IT_SUPPORT]);
        Role::updateOrCreate(['id' => Role::ADMIN_ID], ['name' => Role::ADMIN]);

        $hardware = Category::updateOrCreate(['name' => 'Hardware Issue']);
        $software = Category::updateOrCreate(['name' => 'Software Installation']);
        Category::updateOrCreate(['name' => 'Network/Internet']);
        Category::updateOrCreate(['name' => 'Access/Accounts']);

        $urgent = Tag::updateOrCreate(['name' => 'Urgent']);
        Tag::updateOrCreate(['name' => 'Management']);
        Tag::updateOrCreate(['name' => 'Waiting for User']);
        $newEquipment = Tag::updateOrCreate(['name' => 'New Equipment']);

        $employee = User::updateOrCreate(
            ['email' => 'employee@company.com'],
            [
                'name' => 'John Doe',
                'password' => Hash::make('password'),
                'role_id' => Role::EMPLOYEE_ID,
            ]
        );

        $support = User::updateOrCreate(
            ['email' => 'it@company.com'],
            [
                'name' => 'Jane Smith (IT)',
                'password' => Hash::make('password'),
                'role_id' => Role::IT_SUPPORT_ID,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@company.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role_id' => Role::ADMIN_ID,
            ]
        );

        $printerTicket = Ticket::firstOrCreate(
            ['title' => 'Printer not responding on 3rd floor'],
            [
                'description' => 'The main printer on the 3rd floor is flashing red and refuses to print anything.',
                'status' => Ticket::STATUS_OPEN,
                'priority' => Ticket::PRIORITY_HIGH,
                'user_id' => $employee->id,
                'category_id' => $hardware->id,
            ]
        );
        $printerTicket->tags()->syncWithoutDetaching([$urgent->id]);

        $licenseTicket = Ticket::firstOrCreate(
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
        $licenseTicket->tags()->syncWithoutDetaching([$newEquipment->id]);
    }
}
