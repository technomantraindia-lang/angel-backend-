<?php
namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email'=>'admin@angelprintshop.com'], ['name'=>'Portal Admin','company_name'=>'Angel Print Shop','phone'=>'8200391418','address'=>'F/4, First Floor, Shyamal Complex, New CG Road, Near Kotak Bank, Nigam Nagar, Chandkheda, Ahmedabad, Gujarat','pincode'=>'382424','password'=>Hash::make('Admin@123'),'role'=>'admin','approval_status'=>'approved']);
        User::updateOrCreate(['email'=>'staff@angelprintshop.com'], ['name'=>'Printing Operator','company_name'=>'Angel Print Shop','password'=>Hash::make('Staff@123'),'role'=>'staff','approval_status'=>'approved']);
        $dealer = User::updateOrCreate(['email'=>'dealer@example.com'], ['name'=>'Demo Dealer','company_name'=>'Demo Enterprises','phone'=>'9999999999','address'=>'Vadodara, Gujarat','password'=>Hash::make('Dealer@123'),'role'=>'dealer','approval_status'=>'approved','wallet_balance'=>5000]);
        if (!$dealer->walletTransactions()->exists()) {
            WalletTransaction::create(['user_id'=>$dealer->id,'type'=>'credit','amount'=>5000,'balance_after'=>5000,'description'=>'Opening demo balance']);
        }
        // Clear existing products and discount tiers
        \Illuminate\Support\Facades\DB::table('discount_tiers')->delete();
        Product::query()->delete();

        $quantityTiers = [
            ['min' => 1, 'max' => 5],
            ['min' => 6, 'max' => 10],
            ['min' => 11, 'max' => 25],
            ['min' => 26, 'max' => 50],
            ['min' => 51, 'max' => 75],
            ['min' => 76, 'max' => 100],
            ['min' => 101, 'max' => 150],
            ['min' => 151, 'max' => 200],
            ['min' => 201, 'max' => 250],
            ['min' => 251, 'max' => 300],
            ['min' => 301, 'max' => null],
        ];

        $seedProducts = [
            [
                'category' => 'Color',
                'name' => 'Color Print 100 GSM',
                'print_copy' => 1,
                'amount' => 0.00,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'category' => 'Black & White',
                'name' => 'Black and White Print 100 GSM',
                'print_copy' => 1,
                'amount' => 0.00,
                'sort_order' => 2,
                'is_active' => true,
            ]
        ];

        foreach ($seedProducts as $data) {
            $product = Product::create($data);
            foreach ($quantityTiers as $tier) {
                $product->discountTiers()->create([
                    'min' => $tier['min'],
                    'max' => $tier['max'],
                    'discount' => 0.00,
                ]);
            }
        }
    }
}
