<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\DeliveryIncident;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReturnModel;
use App\Models\Shipment;
use App\Models\Submission;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;

class SupportRecordController extends Controller
{
    public function clients(Request $request) { return $this->users($request, 'client', 'Clients', 'client'); }
    public function vendors(Request $request) { return $this->shops($request); }
    public function orders(Request $request) { return $this->orderRecords($request); }
    public function payments(Request $request) { return $this->paymentRecords($request); }
    public function deliveries(Request $request) { return $this->shipmentRecords($request); }
    public function incidents(Request $request) { return $this->incidentRecords($request); }
    public function returns(Request $request) { return $this->returnRecords($request); }
    public function disputes(Request $request) { return $this->disputeRecords($request); }
    public function messages(Request $request) { return $this->messageRecords($request); }

    private function users(Request $request, string $role, string $title, string $type)
    {
        $query = User::where('role', $role)->latest();
        $this->search($query, $request, ['name', 'first_name', 'last_name', 'email', 'phone']);
        $records = $query->paginate(25)->withQueryString();
        $records->getCollection()->transform(fn ($u) => [
            'id' => $u->id,
            'primary' => $u->name ?: trim($u->first_name . ' ' . $u->last_name),
            'secondary' => $u->email,
            'status' => $u->status,
            'detail' => $u->phone ?: 'Téléphone non renseigné',
            'created_at' => $u->created_at,
            'ticket_query' => ['requester_user_id' => $u->id],
        ]);
        return view('support.records.index', compact('records', 'title', 'type'));
    }

    private function shops(Request $request)
    {
        $query = Shop::with('user')->latest();
        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('city', 'like', "%{$q}%")->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%")));
        }
        $records = $query->paginate(25)->withQueryString();
        $records->getCollection()->transform(fn ($shop) => [
            'id' => $shop->id,
            'primary' => $shop->name,
            'secondary' => $shop->user?->email,
            'status' => $shop->status,
            'detail' => collect([$shop->city, $shop->commune, $shop->district])->filter()->implode(' · '),
            'created_at' => $shop->created_at,
            'ticket_query' => ['shop_id' => $shop->id, 'requester_user_id' => $shop->user_id],
        ]);
        return view('support.records.index', ['records' => $records, 'title' => 'Vendeurs et boutiques', 'type' => 'vendor']);
    }

    private function orderRecords(Request $request)
    {
        $query = Order::with('client')->operational()->latest();
        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($x) => $x->where('order_number', 'like', "%{$q}%")->orWhere('invoice_number', 'like', "%{$q}%")->orWhereHas('client', fn ($u) => $u->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")));
        }
        $records = $query->paginate(25)->withQueryString();
        $records->getCollection()->transform(fn ($o) => [
            'id' => $o->id, 'primary' => $o->order_number, 'secondary' => $o->client?->email,
            'status' => $o->status, 'detail' => number_format((float) $o->total_amount, 0, ',', ' ') . ' FCFA · Paiement ' . ($o->payment_status ?? '—'),
            'created_at' => $o->created_at, 'ticket_query' => ['order_id' => $o->id, 'requester_user_id' => $o->client_id],
        ]);
        return view('support.records.index', ['records' => $records, 'title' => 'Commandes', 'type' => 'order']);
    }

    private function paymentRecords(Request $request)
    {
        $query = Payment::with(['user', 'order'])->latest();
        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($x) => $x->where('reference', 'like', "%{$q}%")->orWhere('transaction_id', 'like', "%{$q}%")->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%")));
        }
        $records = $query->paginate(25)->withQueryString();
        $records->getCollection()->transform(fn ($p) => [
            'id' => $p->id, 'primary' => $p->reference ?: ('Paiement #' . $p->id), 'secondary' => $p->user?->email,
            'status' => $p->status, 'detail' => number_format((float) $p->amount, 0, ',', ' ') . ' FCFA · ' . ($p->method ?? $p->operator ?? '—'),
            'created_at' => $p->created_at, 'ticket_query' => ['payment_id' => $p->id, 'order_id' => $p->order_id, 'requester_user_id' => $p->user_id],
        ]);
        return view('support.records.index', ['records' => $records, 'title' => 'Paiements', 'type' => 'payment']);
    }

    private function shipmentRecords(Request $request)
    {
        $query = Shipment::with(['order.client', 'shop'])->latest();
        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($x) => $x->where('tracking_number', 'like', "%{$q}%")->orWhere('delivery_address', 'like', "%{$q}%")->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$q}%")));
        }
        $records = $query->paginate(25)->withQueryString();
        $records->getCollection()->transform(fn ($s) => [
            'id' => $s->id, 'primary' => $s->tracking_number ?: ('Livraison #' . $s->id), 'secondary' => $s->order?->order_number,
            'status' => $s->status, 'detail' => $s->delivery_address ?: 'Destination non renseignée',
            'created_at' => $s->created_at, 'ticket_query' => ['shipment_id' => $s->id, 'order_id' => $s->order_id, 'shop_id' => $s->shop_id, 'requester_user_id' => $s->order?->client_id],
        ]);
        return view('support.records.index', ['records' => $records, 'title' => 'Livraisons', 'type' => 'delivery']);
    }

    private function incidentRecords(Request $request)
    {
        $query = DeliveryIncident::with(['order', 'shipment'])->latest();
        $this->search($query, $request, ['incident_type', 'description', 'status']);
        $records = $query->paginate(25)->withQueryString();
        $records->getCollection()->transform(fn ($i) => [
            'id' => $i->id, 'primary' => ucfirst(str_replace('_', ' ', $i->incident_type)), 'secondary' => $i->order?->order_number,
            'status' => $i->status, 'detail' => $i->description, 'created_at' => $i->created_at,
            'ticket_query' => ['delivery_incident_id' => $i->id, 'order_id' => $i->order_id, 'shipment_id' => $i->shipment_id],
        ]);
        return view('support.records.index', ['records' => $records, 'title' => 'Incidents logistiques', 'type' => 'incident']);
    }

    private function returnRecords(Request $request)
    {
        $query = ReturnModel::with(['client', 'order'])->latest();
        $this->search($query, $request, ['order_reference', 'product_name', 'reason', 'status']);
        $records = $query->paginate(25)->withQueryString();
        $records->getCollection()->transform(fn ($r) => [
            'id' => $r->id, 'primary' => $r->product_name ?: ('Retour #' . $r->id), 'secondary' => $r->order_reference,
            'status' => $r->status, 'detail' => $r->reason, 'created_at' => $r->created_at,
            'ticket_query' => ['return_id' => $r->id, 'order_id' => $r->order_id, 'requester_user_id' => $r->client_id, 'shop_id' => $r->shop_id],
        ]);
        return view('support.records.index', ['records' => $records, 'title' => 'Retours et remboursements', 'type' => 'return']);
    }

    private function disputeRecords(Request $request)
    {
        $query = Dispute::latest();
        $this->search($query, $request, ['order_reference', 'client_name', 'reason']);
        $records = $query->paginate(25)->withQueryString();
        $records->getCollection()->transform(fn ($d) => [
            'id' => $d->id, 'primary' => $d->order_reference ?: ('Litige #' . $d->id), 'secondary' => $d->client_name,
            'status' => $d->escalated ? 'escalated' : ($d->response ? 'answered' : 'open'), 'detail' => $d->reason, 'created_at' => $d->created_at,
            'ticket_query' => ['dispute_id' => $d->id],
        ]);
        return view('support.records.index', ['records' => $records, 'title' => 'Litiges', 'type' => 'dispute']);
    }

    private function messageRecords(Request $request)
    {
        $query = Submission::latest();
        $this->search($query, $request, ['name', 'email', 'message']);
        $records = $query->paginate(25)->withQueryString();
        $records->getCollection()->transform(fn ($message) => [
            'id' => $message->id,
            'primary' => $message->name,
            'secondary' => $message->email,
            'status' => $message->supportTickets()->exists() ? 'linked' : 'new',
            'detail' => $message->message,
            'created_at' => $message->created_at,
            'ticket_query' => ['submission_id' => $message->id],
        ]);

        return view('support.records.index', ['records' => $records, 'title' => 'Messages entrants', 'type' => 'message']);
    }

    private function search($query, Request $request, array $columns): void
    {
        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($x) use ($q, $columns) {
                foreach ($columns as $column) {
                    $x->orWhere($column, 'like', "%{$q}%");
                }
            });
        }
    }
}
