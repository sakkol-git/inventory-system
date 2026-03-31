<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Modules\Core\Http\Controllers\Controller;

use App\Modules\Inventory\Resources\TransactionResource;
use App\Modules\Inventory\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Transaction::class);
        $query = Transaction::with(['user', 'transactionable'])
            ->latest();

        if ($request->filled('type')) {
            $query->forType($request->input('type'));
        }
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->boolean('recent')) {
            $query->recent();
        }

        return TransactionResource::collection($query->paginate(15));
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction): TransactionResource
    {
        $this->authorize('view', $transaction);

        $transaction->load(['user', 'transactionable']);

        return new TransactionResource($transaction);
    }
}
