<?php

namespace App\Models;

use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'method',
        'transaction_id',
        'amount',
        'status',
        'reference',
        'user_id',
        'client_payment_method_id',
        'operator',
        'mobile_number',
        'type',
        'order_item_ids',
        'provider_payload',
        'paid_at',
        'failed_at',
        'released_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'provider_payload' => 'array',
        'order_item_ids' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public const STATUS_PENDING = PaymentService::STATUS_PENDING;
    public const STATUS_SUCCESS = PaymentService::STATUS_PAID; // Compatibilité ancien code
    public const STATUS_PAID = PaymentService::STATUS_PAID;
    public const STATUS_FAILED = PaymentService::STATUS_FAILED;
    public const STATUS_CANCELLED = PaymentService::STATUS_CANCELLED;
    public const STATUS_REFUNDED = PaymentService::STATUS_REFUNDED;
    public const STATUS_ESCROW_HELD = PaymentService::STATUS_ESCROW_HELD;
    public const STATUS_RELEASED_TO_VENDOR = PaymentService::STATUS_RELEASED_TO_VENDOR;

    public const METHOD_WAVE = PaymentService::METHOD_WAVE;
    public const METHOD_ORANGE_MONEY = PaymentService::METHOD_ORANGE_MONEY;
    public const METHOD_MTN_MONEY = PaymentService::METHOD_MTN_MONEY;
    public const METHOD_MOOV_MONEY = PaymentService::METHOD_MOOV_MONEY;
    public const METHOD_PAYDUNYA = PaymentService::METHOD_PAYDUNYA;
    public const METHOD_FLUTTERWAVE = PaymentService::METHOD_FLUTTERWAVE;
    public const METHOD_CASH_ON_DELIVERY = PaymentService::METHOD_CASH_ON_DELIVERY;
    public const METHOD_MANUAL_PROOF = PaymentService::METHOD_MANUAL_PROOF;

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->reference)) {
                $payment->reference = 'PAY-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));
            }

            if (empty($payment->status)) {
                $payment->status = self::STATUS_PENDING;
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function clientPaymentMethod()
    {
        return $this->belongsTo(ClientPaymentMethod::class);
    }

    public function proofs()
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function businessContactAccess()
    {
        return $this->hasOne(BusinessContactAccess::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->whereIn('status', [self::STATUS_PAID, self::STATUS_ESCROW_HELD, self::STATUS_RELEASED_TO_VENDOR]);
    }

    public function scopeSuccess($query)
    {
        return $this->scopePaid($query);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function markAsSuccess(?string $transactionId = null): bool
    {
        return $this->markAsPaid($transactionId);
    }

    public function markAsPaid(?string $transactionId = null): bool
    {
        $this->status = self::STATUS_PAID;
        $this->paid_at = now();

        if ($transactionId) {
            $this->transaction_id = $transactionId;
        }

        return $this->save();
    }

    public function markAsFailed(?string $reason = null): bool
    {
        $this->status = self::STATUS_FAILED;
        $this->failed_at = now();

        if ($reason) {
            $payload = $this->provider_payload ?? [];
            $payload['reason'] = $reason;
            $this->provider_payload = $payload;
        }

        return $this->save();
    }

    public function isSuccessful(): bool
    {
        return $this->isPaid();
    }

    public function isPaid(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_ESCROW_HELD, self::STATUS_RELEASED_TO_VENDOR], true);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function supportConversations()
    {
        return $this->hasMany(SupportConversation::class);
    }

}
