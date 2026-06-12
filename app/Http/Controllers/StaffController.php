<?php
namespace App\Http\Controllers;
use App\Models\Order; use Illuminate\Http\JsonResponse; use Illuminate\Http\Request; use Illuminate\Validation\Rule;
class StaffController extends Controller
{
    public function queue(Request $request): JsonResponse
    {
        $orders=Order::with(['dealer','items','extraCharges'])
            ->whereNotIn('status',['done','cancelled'])
            ->whereNotIn('staff_status',['picked_up'])
            ->when($request->user()->role==='staff', fn($q)=>$q->where(function($q2) use($request){$q2->whereNull('assigned_staff_id')->orWhere('assigned_staff_id',$request->user()->id);}))->latest('created_at')->get();
        return response()->json($orders);
    }
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data=$request->validate([
            'status'=>['required', Rule::in(['pending', 'started', 'ready', 'picked_up'])], 
            'pickup_note'=>['nullable','string','max:1000']
        ]);
        $updates = [
            'staff_status' => $data['status'],
        ];
        if (isset($data['pickup_note'])) {
            $updates['pickup_note'] = $data['pickup_note'];
        }
        if ($data['status']==='ready') $updates['completed_at']=now();
        if ($data['status']==='picked_up') $updates['picked_up_at']=now();
        $order->update($updates); return response()->json(['message'=>'Job status updated.','order'=>$order->fresh(['dealer','items'])]);
    }
}
