<?php

use App\Models\Contact;
use App\Models\Request;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all requests with a contact value
        $requests = Request::whereNotNull('contact')
            ->where('contact', '!=', '')
            ->get();

        foreach ($requests as $request) {
            $contactName = $request->contact;

            // Skip special cases
            if (in_array(strtolower($contactName), ['néant', 'neant']) ||
                preg_match('/^\d+\//', $contactName)) {
                continue;
            }

            // Split the name into first and last name
            $nameParts = explode(' ', trim($contactName), 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';

            // Create or find the contact
            $contact = Contact::firstOrCreate([
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);

            // Store the contact_id temporarily in the request
            // We'll use this in the next migration
            \DB::table('requests')
                ->where('id', $request->id)
                ->update(['contact_id' => $contact->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clear contact_id values
        \DB::table('requests')->update(['contact_id' => null]);

        // Delete all contacts
        Contact::truncate();
    }
};
