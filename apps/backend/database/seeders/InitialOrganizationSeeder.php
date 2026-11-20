<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\BusinessUnit;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\Platform;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class InitialOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $platform = Platform::query()->updateOrCreate(
            ['slug' => 'oneflo'],
            ['name' => 'OneFlo'],
        );

        Model::unguarded(function () use ($platform): void {
            foreach ($this->organizations() as $organizationData) {
                $organization = Organization::query()->updateOrCreate(
                    ['slug' => $organizationData['slug']],
                    [
                        'platform_id' => $platform->id,
                        'name' => $organizationData['name'],
                        'status' => 'active',
                    ],
                );

                $brand = Brand::query()->updateOrCreate(
                    ['organization_id' => $organization->id, 'slug' => $organizationData['slug']],
                    [
                        'organization_id' => $organization->id,
                        'name' => $organizationData['name'],
                        'is_primary' => true,
                    ],
                );

                $legalEntity = LegalEntity::query()->updateOrCreate(
                    ['organization_id' => $organization->id, 'legal_name' => $organizationData['name']],
                    [
                        'organization_id' => $organization->id,
                        'brand_id' => $brand->id,
                        'legal_name' => $organizationData['name'],
                    ],
                );

                $businessUnit = BusinessUnit::query()->updateOrCreate(
                    ['organization_id' => $organization->id, 'name' => 'Operations'],
                    [
                        'organization_id' => $organization->id,
                        'legal_entity_id' => $legalEntity->id,
                        'name' => 'Operations',
                    ],
                );

                Branch::query()->updateOrCreate(
                    ['organization_id' => $organization->id, 'code' => 'HO'],
                    [
                        'organization_id' => $organization->id,
                        'business_unit_id' => $businessUnit->id,
                        'name' => 'Head Office',
                        'code' => 'HO',
                    ],
                );
            }
        });

        $this->seedSuperAdmin();
    }

    /**
     * Initial brand names live only in this seeder.
     *
     * @return list<array{name: string, slug: string}>
     */
    private function organizations(): array
    {
        return [
            ['name' => 'RupeeDial', 'slug' => 'rupeedial'],
            ['name' => 'Motorcart', 'slug' => 'motorcart'],
            ['name' => 'Propancer', 'slug' => 'propancer'],
            ['name' => 'UdhyamSetu', 'slug' => 'udhyamsetu'],
            ['name' => 'TravelBridge', 'slug' => 'travelbridge'],
        ];
    }

    private function seedSuperAdmin(): void
    {
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            return;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = env('SUPER_ADMIN_NAME', 'OneFlo Super Admin');
        $user->is_active = true;

        if (! $user->exists) {
            $user->password = $password;
            $user->email_verified_at = now();
        }

        $user->save();

        $role = Role::query()->where('slug', 'super-admin')->first();

        if ($role !== null) {
            $user->platformRoles()->syncWithoutDetaching([$role->id]);
        }
    }
}
