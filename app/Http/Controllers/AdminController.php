<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ExtraCharge;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\MonthlyAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $year = (int)$request->query('year', now()->year);
        $this->syncMonthlyAnalytics($year);

        $analytics = MonthlyAnalytics::where('year', $year)->orderBy('month', 'asc')->get();

        $availableYears = Order::selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();
        if (!in_array(now()->year, $availableYears)) {
            $availableYears[] = now()->year;
        }
        sort($availableYears);
        $availableYears = array_reverse(array_unique($availableYears));

        return response()->json([
            'pending_dealers'=>User::where('role','dealer')->where('approval_status','pending')->count(),
            'dealers'=>User::where('role','dealer')->where('approval_status','approved')->count(),
            'new_orders'=>Order::where('status','new')->count(),
            'in_progress'=>Order::where('status','working')->count(),
            'total_sales'=>Order::whereNotIn('status',['cancelled'])->sum('grand_total'),
            'analytics' => $analytics,
            'available_years' => $availableYears,
            'selected_year' => $year
        ]);
    }

    private function syncMonthlyAnalytics(int $year): void
    {
        $orders = Order::whereYear('created_at', $year)
            ->whereNotIn('status', ['cancelled'])
            ->get();

        $grouped = $orders->groupBy(function ($order) {
            return (int)$order->created_at->format('m');
        });

        for ($month = 1; $month <= 12; $month++) {
            $monthOrders = $grouped->get($month, collect());
            $totalOrders = $monthOrders->count();
            $totalEarnings = (float)$monthOrders->sum('grand_total');
            $estimatedProfit = $totalEarnings * 0.20; // 20% profit margin

            MonthlyAnalytics::updateOrCreate(
                ['year' => $year, 'month' => $month],
                [
                    'total_orders' => $totalOrders,
                    'total_earnings' => $totalEarnings,
                    'estimated_profit' => $estimatedProfit
                ]
            );
        }
    }
    public function dealers(): JsonResponse
    {
        return response()->json(User::where('role','dealer')->whereIn('approval_status', ['pending', 'approved'])->with('walletTransactions.order.items')->orderByRaw("CASE approval_status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 ELSE 3 END")->latest()->get());
    }
    public function holdDealers(): JsonResponse
    {
        return response()->json(User::where('role','dealer')->whereIn('approval_status', ['hold', 'rejected'])->with('walletTransactions.order.items')->latest()->get());
    }
    public function setApproval(Request $request, User $dealer): JsonResponse
    {
        abort_unless($dealer->role === 'dealer', 422);
        $data = $request->validate(['approval_status'=>['required', Rule::in(['approved','rejected','banned','hold'])]]);
        $dealer->update($data);
        return response()->json(['message'=>'Dealer status updated.', 'dealer'=>$dealer]);
    }
    public function adjustWallet(Request $request, User $dealer): JsonResponse
    {
        abort_unless($dealer->role === 'dealer', 422);
        $data = $request->validate(['type'=>['required',Rule::in(['credit','debit'])], 'amount'=>['required','numeric','min:0.01'], 'description'=>['required','string','max:255']]);
        $updated = DB::transaction(function () use ($request, $dealer, $data) {
            $locked = User::lockForUpdate()->findOrFail($dealer->id); $amount = (float)$data['amount'];
            $locked->wallet_balance = $data['type'] === 'credit' ? (float)$locked->wallet_balance + $amount : (float)$locked->wallet_balance - $amount; $locked->save();
            WalletTransaction::create(['user_id'=>$locked->id,'type'=>$data['type'],'amount'=>$amount,'balance_after'=>$locked->wallet_balance,'description'=>$data['description'],'created_by'=>$request->user()->id]);
            return $locked;
        });
        return response()->json(['message'=>'Wallet updated.', 'dealer'=>$updated]);
    }
    public function orders(): JsonResponse
    {
        return response()->json(Order::whereHas('dealer', function ($q) {
            $q->where('role', 'dealer');
        })->with(['dealer','assignedStaff','items','extraCharges'])->latest('created_at')->get());
    }
    public function assignStaff(Request $request, Order $order): JsonResponse
    {
        $data=$request->validate(['assigned_staff_id'=>['nullable','exists:users,id'],'deadline_at'=>['nullable','date']]);
        if (!empty($data['assigned_staff_id'])) abort_unless(User::where('id',$data['assigned_staff_id'])->where('role','staff')->exists(), 422, 'Selected user is not printing staff.');
        $order->update($data); return response()->json(['message'=>'Work assignment updated.','order'=>$order]);
    }
    public function staffUsers(): JsonResponse { return response()->json(User::where('role','staff')->get(['id','name','email','plain_password'])); }
    public function addCharge(Request $request, Order $order): JsonResponse
    {
        $data=$request->validate(['description'=>['required','string','max:255'],'amount'=>['required','numeric','not_in:0'],'deduct_from_wallet'=>['sometimes','boolean']]);
        $charge = DB::transaction(function () use ($request,$order,$data) {
            $deduct = (bool)($data['deduct_from_wallet'] ?? true);
            $amount = (float)$data['amount'];
            if ($deduct) {
                $dealer=User::lockForUpdate()->findOrFail($order->dealer_id);
                $dealer->wallet_balance=(float)$dealer->wallet_balance-$amount; $dealer->save();
                
                $type = $amount > 0 ? 'debit' : 'credit';
                $absAmount = abs($amount);
                $txDescription = $amount > 0 ? 'Extra charge: '.$data['description'] : 'Price cut/Refund: '.$data['description'];
                
                WalletTransaction::create([
                    'user_id'=>$dealer->id,
                    'order_id'=>$order->id,
                    'type'=>$type,
                    'amount'=>$absAmount,
                    'balance_after'=>$dealer->wallet_balance,
                    'description'=>$txDescription,
                    'created_by'=>$request->user()->id
                ]);
            }
            $charge=ExtraCharge::create(['order_id'=>$order->id,'description'=>$data['description'],'amount'=>$amount,'deducted_from_wallet'=>$deduct,'added_by'=>$request->user()->id]);
            $order->extra_total=(float)$order->extra_total+$amount; $order->grand_total=(float)$order->subtotal+(float)$order->extra_total; $order->save();
            return $charge;
        });
        return response()->json(['message'=>'Extra charge / price adjustment saved.','charge'=>$charge,'order'=>$order->fresh(['extraCharges'])]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:new,working,done'],
        ]);
        $updates = ['status' => $data['status']];
        if ($data['status'] === 'done') {
            $updates['completed_at'] = now();
        } else {
            $updates['completed_at'] = null;
        }
        $order->update($updates);
        return response()->json(['message' => 'Order status updated.', 'order' => $order->fresh(['dealer', 'assignedStaff', 'items', 'extraCharges'])]);
    }

    public function shareReceipt(Request $request, Order $order): JsonResponse
    {
        $order->update(['receipt_shared' => true]);
        return response()->json(['message' => 'Receipt shared with dealer.', 'order' => $order->fresh(['dealer', 'assignedStaff', 'items', 'extraCharges'])]);
    }

    public function createStaff(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $staff = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'plain_password' => $data['password'],
            'role' => 'staff',
            'approval_status' => 'approved',
            'wallet_balance' => 0
        ]);

        return response()->json([
            'message' => 'Staff member created successfully.',
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email
            ]
        ], 201);
    }

    public function updateStaff(Request $request, User $staff): JsonResponse
    {
        abort_unless($staff->role === 'staff', 422, 'Selected user is not a staff member.');
        
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($staff->id)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);
        
        $updates = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];
        
        if (!empty($data['password'])) {
            $updates['password'] = bcrypt($data['password']);
            $updates['plain_password'] = $data['password'];
        }
        
        $staff->update($updates);
        
        return response()->json([
            'message' => 'Staff member updated successfully.',
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'plain_password' => $staff->plain_password
            ]
        ]);
    }

    public function destroyStaff(User $staff): JsonResponse
    {
        abort_unless($staff->role === 'staff', 422, 'Selected user is not a staff member.');
        Order::where('assigned_staff_id', $staff->id)->update(['assigned_staff_id' => null]);
        $staff->delete();
        return response()->json(['message' => 'Staff member deleted successfully.']);
    }

    public function b2cCustomers(): JsonResponse
    {
        return response()->json(Customer::query()->latest()->get());
    }

    public function destroyB2CCustomer(Customer $customer): JsonResponse
    {
        $customer->delete();
        return response()->json(['message' => 'B2C customer account deleted successfully.']);
    }

    public function b2cOrders(): JsonResponse
    {
        return response()->json(Order::whereHas('dealer', function ($q) {
            $q->where('role', 'customer');
        })->with(['dealer', 'assignedStaff', 'items', 'extraCharges'])->latest('created_at')->get());
    }
}
